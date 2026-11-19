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
        if ($request->query('_editor') === '1' && $request->isMethod('GET') && $request->user()?->esCurador()
            && in_array($request->route()?->getName(), \App\Support\EditorPortal::paginas(), true)
            && $response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->setContent(\App\Support\VistaEditorialInerte::sanear($response->getContent()));
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        if ($request->user()) {
            $response->headers->set('X-Portal-Session', 'authenticated');
        }
        if ($request->user() && $request->routeIs('home', 'portal.*')) {
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
