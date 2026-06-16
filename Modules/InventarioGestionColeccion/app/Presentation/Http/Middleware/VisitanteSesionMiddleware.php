<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege el mapa del visitante: solo deja pasar si existe una sesión de visitante
 * abierta por un enlace QR válido y aún no expirada. El visitante no es un usuario
 * autenticado del sistema; su único acceso es esta sesión efímera, de modo que no
 * puede alcanzar ninguna otra área (las del curador van tras `auth`/`role:curador`).
 */
final class VisitanteSesionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $visitanteId = $request->session()->get('visitante_id');
        $expiraEn = $request->session()->get('visitante_expira_en');

        if ($visitanteId === null || $expiraEn === null || now()->getTimestamp() > (int) $expiraEn) {
            $request->session()->forget(['visitante_id', 'visitante_nombre', 'visitante_expira_en']);

            return redirect()->route('inventario.visitante.acceso-invalido');
        }

        return $next($request);
    }
}
