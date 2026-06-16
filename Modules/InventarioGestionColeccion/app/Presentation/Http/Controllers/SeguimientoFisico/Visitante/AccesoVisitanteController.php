<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Visitante;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ValidarAccesoVisitante\ValidarAccesoVisitanteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ValidarAccesoVisitante\ValidarAccesoVisitanteInput;

/**
 * Aterrizaje del QR del visitante: la URL viene firmada y con expiración (~5 min),
 * que el middleware `signed` ya validó. Aquí se comprueba la versión de acceso
 * vigente y, si todo cuadra, se abre la sesión efímera del visitante y se le lleva
 * directo al mapa. Cualquier fallo cae en la pantalla de acceso inválido.
 */
final class AccesoVisitanteController
{
    /**
     * Minutos que dura la sesión del visitante una vez dentro: se alinea con la
     * expiración de la URL firmada para que el acceso sea verdaderamente efímero.
     */
    private const MINUTOS_SESION = 5;

    public function __invoke(Request $request, ValidarAccesoVisitanteHandler $handler): RedirectResponse
    {
        $resultado = $handler->handle(new ValidarAccesoVisitanteInput(
            visitanteId: (string) $request->query('visitanteId', ''),
            version: (int) $request->query('version', 0),
        ));

        if (! $resultado->valido) {
            return redirect()->route('inventario.visitante.acceso-invalido');
        }

        $request->session()->put('visitante_id', $resultado->visitanteId);
        $request->session()->put('visitante_nombre', $resultado->nombre);
        $request->session()->put('visitante_expira_en', now()->addMinutes(self::MINUTOS_SESION)->getTimestamp());

        return redirect()->route('inventario.visitante.mapa');
    }
}
