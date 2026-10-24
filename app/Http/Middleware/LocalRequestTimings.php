<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LocalRequestTimings
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local')) return $next($request);

        $inicio = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $arranque = (microtime(true) - $inicio) * 1000;
        $response = $next($request);
        $consultas = array_sum(array_map(
            static fn ($connection): float => $connection->totalQueryDuration(),
            app('db')->getConnections(),
        ));
        // Sólo duraciones: nunca consultas, parámetros, credenciales ni datos del usuario.
        $metricas = sprintf('app;dur=%.2f, bootstrap;dur=%.2f, db;dur=%.2f',
            (microtime(true) - $inicio) * 1000, $arranque, $consultas);
        foreach (['panel_datos', 'panel_vista', 'panel_familias'] as $nombre) {
            $duracion = $request->attributes->get($nombre.'_ms');
            if (is_numeric($duracion)) $metricas .= sprintf(', %s;dur=%.2f', $nombre, $duracion);
        }
        $previo = $response->headers->get('Server-Timing');
        $response->headers->set('Server-Timing', $previo ? $previo.', '.$metricas : $metricas);
        return $response;
    }
}
