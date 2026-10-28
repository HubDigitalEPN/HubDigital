<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** El mismo permiso gobierna el control visible y la acción en servidor. */
final class BotonesSistema
{
    public static function definiciones(): array
    {
        return config('botones', []);
    }

    public static function permisos(string $boton, RolUsuario $rol): array
    {
        $def = self::definiciones()[$boton] ?? null;
        if (! $def || ! in_array($rol->value, $def['elegibles'], true)) {
            return ['ver' => false, 'usar' => false];
        }

        // Sin caché entre peticiones: una revocación rige incluso en un formulario abierto.
        try {
            $fila = DB::table('usuarios.permisos_botones')->where('boton', $boton)->where('rol', $rol->value)->first();
        } catch (\Throwable $error) {
            Log::warning('No se pudieron consultar los permisos de botones.', ['tipo' => $error::class]);

            return ['ver' => false, 'usar' => false];
        }

        $predeterminado = in_array($rol->value, $def['roles'], true);
        $ver = $fila ? (bool) $fila->ver : $predeterminado;

        return ['ver' => $ver, 'usar' => $ver && ($fila ? (bool) $fila->usar : $predeterminado)];
    }

    private static function consultar(string $boton, string $accion, ?User $usuario): bool
    {
        $usuario ??= auth()->user();
        if (! $usuario || ! $usuario->hasVerifiedEmail() || $usuario->must_change_password) return false;
        $usuario->load('roles');
        $rol = $usuario->rolActivo();
        if (! $usuario->tieneRol($rol)) return false;

        return self::permisos($boton, $rol)[$accion];
    }

    public static function visible(string $boton, ?User $usuario = null): bool
    {
        return self::consultar($boton, 'ver', $usuario);
    }

    public static function permitido(string $boton, ?User $usuario = null): bool
    {
        return self::consultar($boton, 'usar', $usuario);
    }

    public static function autorizar(string $boton): void
    {
        abort_unless(self::permitido($boton), 403, 'Tu perfil activo no tiene permiso para usar esta acción.');
    }
}
