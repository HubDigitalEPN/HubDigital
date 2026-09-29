<?php

namespace App\Models;

use App\Enums\RolUsuario;
use App\Notifications\Auth\QueuedResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'email_verified_at', 'must_change_password', 'rol', 'cargo', 'institucion'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, HasUuids, Notifiable, TwoFactorAuthenticatable;

    protected $table = 'usuarios.users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
            'rol' => RolUsuario::class,
        ];
    }

    /**
     * Get the user's full name (backward-compatible accessor).
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * Una cuenta tiene una sola identidad de correo, sin diferencias por
     * mayúsculas o espacios accidentales. La segunda columna está protegida
     * por un índice único en base de datos para cubrir también condiciones de
     * carrera entre dos registros simultáneos.
     */
    protected function email(): Attribute
    {
        return Attribute::set(function (mixed $value): array {
            $normalizado = self::normalizarEmail((string) $value);

            return [
                'email' => $normalizado,
                'email_normalizado' => $normalizado,
            ];
        });
    }

    public static function normalizarEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword((string) $token));
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::upper(
            Str::substr($this->first_name, 0, 1).Str::substr($this->last_name, 0, 1)
        );
    }

    /**
     * Roles asignados a la cuenta (membresía). Fuente de verdad: pivote
     * usuarios.user_roles. La columna escalar `rol` es solo el rol primario.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    /**
     * @return Collection<int, RolUsuario>
     */
    public function rolesAsignados(): Collection
    {
        return $this->roles->pluck('rol');
    }

    public function tieneRol(RolUsuario $rol): bool
    {
        return $this->rolesAsignados()->contains($rol);
    }

    public function tieneAlgunRol(RolUsuario ...$roles): bool
    {
        foreach ($roles as $rol) {
            if ($this->tieneRol($rol)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rol con el que la cuenta opera en la sesión actual ("sombrero" activo).
     * Se guarda en sesión; si falta o ya no pertenece a la cuenta, cae al rol
     * primario (columna) o al primer rol asignado y re-guarda (auto-sana).
     */
    public function rolActivo(): RolUsuario
    {
        $asignados = $this->rolesAsignados();

        $enSesion = session('rol_activo');
        if ($enSesion !== null) {
            $rol = RolUsuario::tryFrom($enSesion);
            if ($rol !== null && $asignados->contains($rol)) {
                return $rol;
            }
        }

        $porDefecto = $asignados->contains($this->rol)
            ? $this->rol
            : ($asignados->first() ?? $this->rol);

        session(['rol_activo' => $porDefecto->value]);

        return $porDefecto;
    }

    /**
     * Conmuta el rol activo (solo entre roles que la cuenta posee).
     */
    public function activarRol(RolUsuario $rol): void
    {
        if (! $this->tieneRol($rol)) {
            return;
        }

        session(['rol_activo' => $rol->value]);
    }

    /**
     * Añade un rol a la cuenta (membresía). Idempotente. Preserva la invariante
     * de que CURADOR es exclusivo de los roles auto-servicio.
     */
    public function asignarRol(RolUsuario $rol): void
    {
        if ($this->tieneRol($rol)) {
            return;
        }

        $asignados = $this->rolesAsignados();

        $esRolInterno = in_array($rol, RolUsuario::rolesInternos(), true);
        $tieneRolInterno = $asignados->contains(
            fn (RolUsuario $asignado): bool => in_array($asignado, RolUsuario::rolesInternos(), true)
        );

        if ($esRolInterno && $asignados->isNotEmpty()) {
            throw new \DomainException('Los roles internos de la EPN no pueden combinarse con otros roles.');
        }

        if ($tieneRolInterno) {
            throw new \DomainException('Una cuenta interna de la EPN no puede asumir otros roles.');
        }

        $this->roles()->create(['rol' => $rol->value]);
        $this->load('roles');
    }

    /**
     * Reemplaza las membresías por una selección explícita de administración.
     *
     * El rol primario siempre queda dentro de las membresías y conserva la
     * compatibilidad de la columna histórica `rol`. Las restricciones entre
     * perfiles internos y de autoservicio se validan antes de escribir.
     *
     * @param  list<RolUsuario>  $roles
     */
    public function sincronizarRoles(array $roles, RolUsuario $rolPrimario): void
    {
        $roles = collect($roles)
            ->filter(static fn (mixed $rol): bool => $rol instanceof RolUsuario)
            ->unique(fn (RolUsuario $rol): string => $rol->value)
            ->values();

        if ($roles->isEmpty() || ! $roles->contains($rolPrimario)) {
            throw new \DomainException('Selecciona al menos un rol y define un rol operativo principal.');
        }

        $internos = $roles->filter(
            static fn (RolUsuario $rol): bool => in_array($rol, RolUsuario::rolesInternos(), true),
        );

        if ($internos->count() > 1 || ($internos->isNotEmpty() && $roles->count() > 1)) {
            throw new \DomainException('Un perfil interno de la EPN no puede combinarse con otros roles.');
        }

        $this->roles()->delete();
        $this->fill(['rol' => $rolPrimario])->save();

        foreach ($roles as $rol) {
            $this->roles()->create(['rol' => $rol->value]);
        }

        $this->load('roles');
    }

    public function esCurador(): bool
    {
        return $this->tieneAlgunRol(RolUsuario::CURADOR, RolUsuario::ADMIN);
    }

    public function esReceptor(): bool
    {
        return $this->tieneRol(RolUsuario::RECEPTOR);
    }

    public function esDepositante(): bool
    {
        return $this->tieneRol(RolUsuario::DEPOSITANTE);
    }

    public function esPrestamista(): bool
    {
        return $this->tieneRol(RolUsuario::PRESTAMISTA);
    }

    public function esAdministrador(): bool
    {
        return $this->tieneRol(RolUsuario::ADMIN);
    }

    public function esUsuarioInterno(): bool
    {
        return $this->tieneAlgunRol(...RolUsuario::rolesInternos());
    }

    /**
     * Capacidades mínimas de un token emitido por el inicio de sesión API.
     * Cada rol recibe únicamente las capacidades necesarias para sus trámites.
     *
     * @return list<string>
     */
    public function habilidadesApiInteractiva(): array
    {
        $habilidades = [];

        if ($this->esDepositante() || $this->esAdministrador()) {
            $habilidades[] = 'depositos:gestionar';
        }
        if ($this->esPrestamista() || $this->esAdministrador()) {
            $habilidades[] = 'prestamos:gestionar';
        }
        if ($this->esCurador() || $this->esAdministrador()) {
            $habilidades[] = 'curaduria:gestionar';
        }
        if ($this->esReceptor() || $this->esAdministrador()) {
            $habilidades[] = 'recepcion:gestionar';
        }

        return array_values(array_unique($habilidades));
    }
}
