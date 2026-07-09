<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use Modules\CatalogoPublico\Application\Ports\GeneradorMarcaAguaPort;

final class MarcaAguaAdapter implements GeneradorMarcaAguaPort
{
    private const FUENTE = __DIR__.'/../../../resources/fonts/DejaVuSans-Bold.ttf';

    public function aplicar(string $contenido, string $texto): string
    {
        $image = ImageManager::usingDriver(new Driver)->decodeBinary($contenido);

        // Tamaño relativo al ancho de la imagen para que sea legible en cualquier resolución.
        $tamanio = (int) max(12, $image->width() * 0.025);

        // Esquina inferior izquierda, con un margen proporcional al ancho.
        $margen = (int) max(8, $image->width() * 0.015);
        $x = $margen;
        $y = $image->height() - $margen;

        $textoConCopyright = '© '.$texto.' '.date('Y');

        $image->text($textoConCopyright, $x, $y, function (FontFactory $font) use ($tamanio): void {
            $font->filename(self::FUENTE);
            $font->size($tamanio);
            $font->color('rgba(0, 0, 0, 0.85)');
            $font->align('left', 'bottom');
        });

        return (string) $image->encode();
    }
}
