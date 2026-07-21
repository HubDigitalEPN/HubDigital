<?php

namespace App\Models;

use App\Enums\RolUsuario;
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

#[Fillable(['first_name', 'last_name', 'email', 'password', 'rol', 'cargo', 'institucion'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable, TwoFactorAuthenticatable;

    protected $table = 'usuarios.users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'email_verified_at' => 'datetime',
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

        if ($rol === RolUsuario::CURADOR && $asignados->isNotEmpty()) {
            throw new \DomainException('El rol CURADOR no puede combinarse con otros roles.');
        }

        if ($asignados->contains(RolUsuario::CURADOR)) {
            throw new \DomainException('Una cuenta curador no puede asumir otros roles.');
        }

        $this->roles()->create(['rol' => $rol->value]);
        $this->load('roles');
    }

    public function esCurador(): bool
    {
        return $this->tieneRol(RolUsuario::CURADOR);
    }

    public function esDepositante(): bool
    {
        return $this->tieneRol(RolUsuario::DEPOSITANTE);
    }

    public function esPrestamista(): bool
    {
        return $this->tieneRol(RolUsuario::PRESTAMISTA);
    }
}
