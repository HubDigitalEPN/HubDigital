<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Servicio de presentación que codifica visualmente un código de lote ya asignado
 * por el dominio (p. ej. "LOTE-A8B39C") como una imagen QR escaneable en formato SVG.
 *
 * No contiene lógica de negocio: solo traduce una cadena a su representación gráfica.
 */
final class GeneradorQrSvg
{
    /**
     * Genera el marcado SVG de un código QR para incrustar inline en una vista Blade.
     *
     * @param  string  $contenido  Cadena a codificar (código QR del lote).
     * @param  int  $tamanio  Lado del SVG en píxeles.
     */
    public static function svg(string $contenido, int $tamanio = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($tamanio, 1),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($contenido);

        // Eliminar el prólogo XML para permitir la incrustación inline en HTML.
        return preg_replace('/^<\?xml.*?\?>\s*/', '', $svg) ?? $svg;
    }
}
