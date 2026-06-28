<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\Contracts\View\View;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr\ResolverCodigoQrHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverCodigoQr\ResolverCodigoQrInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Exceptions\EspecimenNoEncontradoException;

/**
 * Aterrizaje del QR físico del espécimen. El `payload` es un token opaco e
 * inadivinable impreso en la etiqueta: actúa como capacidad de acceso, de modo
 * que quien escanea en campo ve la ficha sin autenticarse manualmente. Si el
 * payload no resuelve a ningún espécimen, se muestra la pantalla de no encontrado.
 */
final class ResolverQrController
{
    public function __invoke(string $payload, ResolverCodigoQrHandler $handler): View
    {
        try {
            $ficha = $handler->handle(new ResolverCodigoQrInput(payload: $payload));
        } catch (EspecimenNoEncontradoException) {
            return view('inventariogestioncoleccion::admin.taxonomia.especimenes.qr-no-encontrado');
        }

        return view('inventariogestioncoleccion::admin.taxonomia.especimenes.qr-ficha', [
            'ficha' => $ficha,
        ]);
    }
}
