<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DescargasCatalogo
{
    public static function equipoActivo(?User $usuario = null): bool
    {
        $usuario ??= auth()->user();
        return $usuario !== null && in_array($usuario->rolActivo(), [\App\Enums\RolUsuario::CURADOR, \App\Enums\RolUsuario::AYUDANTE_CURADOR, \App\Enums\RolUsuario::ADMIN], true);
    }

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

    public static function permitidas(?User $usuario = null, string $boton = 'catalogo.resultados'): bool
    {
        $usuario ??= auth()->user();
        if (!$usuario || !$usuario->hasVerifiedEmail() || $usuario->must_change_password) return false;
        return BotonesSistema::permitido($boton, $usuario)
            && (self::equipoActivo($usuario) || self::publicasHabilitadas());
    }

    public static function autorizar(string $boton = 'catalogo.resultados'): void
    {
        abort_unless(self::permitidas(boton: $boton), 403, 'Las descargas requieren una cuenta verificada y la autorización de administración para el perfil activo.');
    }

    public static function visibles(string $boton): bool
    {
        $usuario = auth()->user();
        return BotonesSistema::visible($boton, $usuario) && $usuario
            && (self::equipoActivo($usuario) || self::publicasHabilitadas());
    }
}
