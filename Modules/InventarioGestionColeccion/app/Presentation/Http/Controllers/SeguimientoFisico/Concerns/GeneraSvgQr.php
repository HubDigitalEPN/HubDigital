<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Genera el QR como SVG en el servidor con BaconQrCode (ya presente en el
 * proyecto para el 2FA). Sin librería JS ni CDN: el SVG viaja en la respuesta
 * y se puede imprimir/descargar tal cual, incluso sin internet.
 */
trait GeneraSvgQr
{
    protected function generarSvgQr(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(220, 1),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($url);

        // Quita el prólogo XML inicial para poder incrustar el SVG inline en HTML.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }
}
