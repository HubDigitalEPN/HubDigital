<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class PortalCopy
{
    private ?array $guardados = null;

    public static function text(string $clave): string
    {
        $defecto = config('portal-copy.'.$clave);
        if (! is_string($defecto)) throw new \InvalidArgumentException('Texto del portal no definido: '.$clave);
        return (string) (self::all()[$clave] ?? $defecto);
    }

    public static function all(): array
    {
        $servicio = app(self::class);
        if ($servicio->guardados !== null) return $servicio->guardados;
        try {
            if (! Schema::hasTable('usuarios.textos_portal')) return $servicio->guardados = [];
            return $servicio->guardados = Cache::remember('portal-copy-overrides-v1', 300, static fn (): array =>
                DB::table('usuarios.textos_portal')->pluck('contenido', 'clave')->all()
            );
        } catch (\Throwable $error) {
            Log::warning('No se pudieron cargar los textos del portal.', ['tipo' => $error::class]);
            return $servicio->guardados = [];
        }
    }

    /** Conserva los números, rutas de imágenes y demás datos del indicador. */
    public static function configuration(string $nombre): array
    {
        if (! in_array($nombre, ['indices_portal', 'figuras_portal'], true)) throw new \InvalidArgumentException('Configuración editorial no permitida.');
        $grupo = $nombre === 'indices_portal' ? 'indicadores' : 'figuras';
        $datos = config($nombre, []);
        array_walk_recursive($datos, static function (&$valor) use ($grupo): void {
            if (! is_string($valor)) return;
            $clave = $grupo.'.texto_'.substr(hash('sha256', $valor), 0, 12);
            if (is_string(config('portal-copy.'.$clave))) $valor = self::text($clave);
        });
        return $datos;
    }

    public static function forget(): void
    {
        app(self::class)->guardados = null;
        Cache::forget('portal-copy-overrides-v1');
    }
}
