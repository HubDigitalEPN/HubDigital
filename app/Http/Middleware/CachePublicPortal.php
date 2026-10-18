<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CachePublicPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->user() && $request->routeIs('portal.*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        $initial = $request->routeIs('home') && $request->query() === [];
        $initial = $initial || ($request->routeIs('portal.catalogo')
            && in_array($request->query(), [[], ['vista' => 'mapa']], true));
        if ($initial && $request->isMethod('GET') && ! $request->user() && $response->getStatusCode() === 200) {
            // Solo contenido público anónimo; las páginas de cuentas y expedientes quedan fuera.
            $response->headers->set('X-Portal-Public-Cache', 'document');
        }

        return $response;
    }
}
