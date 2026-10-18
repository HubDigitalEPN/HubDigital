<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class AccesoCatalogo
{
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
}
