<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class AccesoCatalogo
{
    public const CONSULTA = 'inventario.taxonomia.especimenes';

    public const IMPORTACION = 'inventario.taxonomia.importar';

    public static function completo(?User $usuario = null): bool
    {
        $usuario ??= auth()->user();

        return $usuario !== null && $usuario->hasVerifiedEmail()
            && ! $usuario->must_change_password && $usuario->esCurador();
    }

    public static function autorizar(): void
    {
        abort_unless(self::completo(), 403, 'Esta función está disponible para el equipo de curaduría.');
    }

    public static function permitido(string $ruta, ?string $accion = null, ?User $usuario = null, ?string $rolSolicitante = null): bool
    {
        $usuario ??= auth()->user();
        if (!$usuario || !$usuario->hasVerifiedEmail() || $usuario->must_change_password) {
            return false;
        }
        if ($usuario->esCurador()) {
            return true;
        }

        $rol = $rolSolicitante === null
            ? $usuario->rolActivo()
            : $usuario->rolesAsignados()->firstWhere('value', $rolSolicitante);

        return $rol !== null && $usuario->tieneRol($rol) && app(MenuLateral::class)->accesoDelegado($ruta, $rol, $accion);
    }

    public static function autorizarPantalla(string $ruta, ?string $accion = null): void
    {
        abort_unless(self::permitido($ruta, $accion), 403,
            'Tu perfil no tiene acceso a esta pantalla del catálogo.');
    }
}
