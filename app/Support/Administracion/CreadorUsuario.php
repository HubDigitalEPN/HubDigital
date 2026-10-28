<?php

declare(strict_types=1);

namespace App\Support\Administracion;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreadorUsuario
{
    /**
     * @param  array{first_name:string,last_name:string,email:string,password:string,cargo?:?string,institucion?:?string}  $datos
     */
    public function crear(#[\SensitiveParameter] array $datos, RolUsuario $rol, bool $creadoPorAdministrador = false): User
    {
        $email = User::normalizarEmail($datos['email']);

        // Bcrypt cuenta bytes, mientras que la regla max de texto cuenta
        // caracteres. Se rechaza también el exceso con caracteres multibyte.
        if (strlen($datos['password']) > 72) {
            throw ValidationException::withMessages([
                'password' => 'La contraseña es demasiado larga; utiliza menos caracteres.',
            ]);
        }

        if (in_array($rol, RolUsuario::rolesInternos(), true)
            && ! ($creadoPorAdministrador && $rol === RolUsuario::ADMIN)
            && ! $this->esCorreoInstitucional($email)) {
            throw ValidationException::withMessages([
                'email' => 'Los roles internos requieren un correo institucional autorizado.',
            ]);
        }

        if ($rol === RolUsuario::DEPOSITANTE) {
            $faltantes = collect(['cargo', 'institucion'])
                ->filter(fn (string $campo): bool => trim((string) ($datos[$campo] ?? '')) === '')
                ->mapWithKeys(fn (string $campo): array => [$campo => 'Este dato es obligatorio para el perfil investigador depositante.'])
                ->all();

            if ($faltantes !== []) {
                throw ValidationException::withMessages($faltantes);
            }
        }

        try {
            $usuario = DB::transaction(function () use ($datos, $email, $rol, $creadoPorAdministrador): User {
                $usuario = User::create([
                    'first_name' => trim($datos['first_name']),
                    'last_name' => trim($datos['last_name']),
                    'email' => $email,
                    'password' => Hash::make($datos['password']),
                    'email_verified_at' => $creadoPorAdministrador ? now() : null,
                    'must_change_password' => $creadoPorAdministrador,
                    'rol' => $rol,
                    'cargo' => $this->valorOpcional($datos['cargo'] ?? null),
                    'institucion' => $this->valorOpcional($datos['institucion'] ?? null),
                ]);

                $usuario->asignarRol($rol);

                return $usuario;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta asociada a este correo.',
            ]);
        }

        // También las altas administrativas deben demostrar control del correo.
        // En el bootstrap esta transacción puede estar anidada: el mensaje solo
        // se envía después de confirmar definitivamente la cuenta y su rol.
        if (! $creadoPorAdministrador) {
            DB::afterCommit(static fn () => event(new Registered($usuario)));
        }

        return $usuario;
    }

    private function esCorreoInstitucional(string $email): bool
    {
        foreach (config('auth.internal_email_domains', []) as $dominio) {
            if (Str::endsWith($email, '@'.Str::lower((string) $dominio))) {
                return true;
            }
        }

        return false;
    }

    private function valorOpcional(?string $valor): ?string
    {
        $normalizado = trim((string) $valor);

        return $normalizado === '' ? null : $normalizado;
    }
}
