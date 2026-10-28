<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class CrearUsuarioInternoCommand extends Command
{
    protected $signature = 'usuarios:crear-interno
        {email : Correo institucional de la cuenta}
        {rol : CURADOR, AYUDANTE_CURADOR, RECEPTOR o ADMIN}
        {--nombre= : Nombre}
        {--apellido= : Apellido}';

    protected $description = 'Crea de forma interactiva una cuenta interna EPN sin exponer su contraseña';

    public function handle(): int
    {
        $email = User::normalizarEmail((string) $this->argument('email'));
        $rol = RolUsuario::tryFrom(Str::upper((string) $this->argument('rol')));
        $nombre = trim((string) ($this->option('nombre') ?: $this->ask('Nombre')));
        $apellido = trim((string) ($this->option('apellido') ?: $this->ask('Apellido')));

        if (! in_array($rol, RolUsuario::rolesInternos(), true)) {
            $this->error('El rol debe ser CURADOR, AYUDANTE_CURADOR, RECEPTOR o ADMIN.');

            return self::FAILURE;
        }

        if (! $this->esDominioInstitucional($email)) {
            $this->error('El correo no pertenece a un dominio institucional autorizado.');

            return self::FAILURE;
        }

        if (User::query()->where('email_normalizado', $email)->exists()) {
            $this->error('La cuenta ya existe. No se modificaron su contraseña ni sus roles.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Contraseña temporal');
        $confirmation = (string) $this->secret('Confirma la contraseña temporal');

        $validator = Validator::make([
            'email' => $email,
            'first_name' => $nombre,
            'last_name' => $apellido,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email_normalizado')],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($email, $rol, $nombre, $apellido, $password): void {
                $user = User::create([
                    'email' => $email,
                    'first_name' => $nombre,
                    'last_name' => $apellido,
                    'password' => $password,
                    'rol' => $rol,
                    'institucion' => 'Escuela Politécnica Nacional',
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->asignarRol($rol);
            });
        } catch (UniqueConstraintViolationException) {
            $this->error('La cuenta fue creada simultáneamente por otro proceso.');

            return self::FAILURE;
        } finally {
            unset($password, $confirmation);
        }

        $this->info('Cuenta interna creada. Solicita cambiar la contraseña y activar 2FA en el primer ingreso.');

        return self::SUCCESS;
    }

    private function esDominioInstitucional(string $email): bool
    {
        foreach (config('auth.internal_email_domains', []) as $domain) {
            if (Str::endsWith($email, '@'.Str::lower((string) $domain))) {
                return true;
            }
        }

        return false;
    }
}
