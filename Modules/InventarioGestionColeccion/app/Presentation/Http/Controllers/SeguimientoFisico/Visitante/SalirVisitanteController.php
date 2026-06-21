<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Visitante;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cierra la sesión efímera del visitante y lo devuelve al portal público. Olvida
 * las claves de sesión del visitante para que cualquier intento posterior de abrir
 * el mapa caiga en la pantalla de acceso inválido.
 */
final class SalirVisitanteController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->session()->forget(['visitante_id', 'visitante_nombre', 'visitante_expira_en']);

        return redirect()->route('portal.catalogo');
    }
}
