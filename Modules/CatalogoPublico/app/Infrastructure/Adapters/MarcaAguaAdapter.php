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
        $tamanio = (int) max(24, $image->width() * 0.07);

        // Centrada horizontalmente y elevada por encima del centro vertical.
        $x = (int) ($image->width() / 2);
        $y = (int) ($image->height() * 0.4);

        $image->text($texto, $x, $y, function (FontFactory $font) use ($tamanio): void {
            $font->filename(self::FUENTE);
            $font->size($tamanio);
            $font->color('rgba(255, 255, 255, 0.85)');
            $font->align('center', 'center');
        });

        return (string) $image->encode();
    }
}
