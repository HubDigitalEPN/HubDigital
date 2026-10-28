<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Console\Command;

/** Operación administrativa explícita en consola; nunca crea ni verifica una cuenta. */
final class AsignarPerfilesUsuarioCommand extends Command
{
    protected $signature = 'usuarios:asignar-perfiles {email} {roles* : Roles adicionales que se asignarán}';
    protected $description = 'Añade perfiles a una cuenta existente y conserva su perfil principal y sus datos';

    public function handle(): int
    {
        $usuario = User::query()->with('roles')->where('email_normalizado', User::normalizarEmail($this->argument('email')))->first();
        if (! $usuario) {
            $this->error('La cuenta no existe. Créala desde administración antes de asignar perfiles.');
            return self::FAILURE;
        }
        $roles = [];
        foreach ($this->argument('roles') as $valor) {
            $rol = RolUsuario::tryFrom(strtoupper($valor));
            if (! $rol) {
                $this->error('Perfil desconocido. No se modificó la cuenta.');
                return self::FAILURE;
            }
            $roles[] = $rol;
        }
        $usuario->sincronizarRoles([...$usuario->rolesAsignados()->all(), ...$roles], $usuario->rol);
        $this->info('Perfiles asignados. Se conservó el perfil principal.');
        return self::SUCCESS;
    }
}
