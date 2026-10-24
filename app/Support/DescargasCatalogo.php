<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DescargasCatalogo
{
    public static function publicasHabilitadas(): bool
    {
        try {
            return Schema::hasTable('usuarios.configuracion_catalogo')
                && (bool) DB::table('usuarios.configuracion_catalogo')->where('clave', 'descargas_autoservicio')->value('habilitado');
        } catch (\Throwable $error) {
            \Illuminate\Support\Facades\Log::warning('No se pudo consultar el permiso de descargas.', ['tipo' => $error::class]);
            return false;
        }
    }

    public static function permitidas(?User $usuario = null): bool
    {
        $usuario ??= auth()->user();
        if (!$usuario || !$usuario->hasVerifiedEmail() || $usuario->must_change_password) return false;
        return $usuario->esCurador() || self::publicasHabilitadas();
    }

    public static function autorizar(): void
    {
        abort_unless(self::permitidas(), 403, 'Las descargas requieren una cuenta verificada y la autorización de administración.');
    }
}
