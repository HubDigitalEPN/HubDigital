<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Support;

use Modules\CatalogoPublico\Application\Ports\AlmacenamientoImagenesPort;
use Modules\CatalogoPublico\Domain\ValueObjects\ArchivoImagen;

/**
 * Almacenamiento volátil para Behat. No toca disco: registra los nombres
 * "guardados" para poder verificar que (no) se almacenó una imagen.
 */
final class FakeAlmacenamientoImagenes implements AlmacenamientoImagenesPort
{
    /** @var array<string, string> nombreOriginal => contenido */
    private array $almacenadas = [];

    public function guardar(string $contenido, string $nombreOriginal): ArchivoImagen
    {
        $this->almacenadas[$nombreOriginal] = $contenido;

        return ArchivoImagen::crear(
            nombreOriginal: $nombreOriginal,
            ruta: 'divulgacion/imagenes/'.$nombreOriginal,
            disco: 'public',
        );
    }

    public function eliminar(ArchivoImagen $archivo): void
    {
        unset($this->almacenadas[$archivo->nombreOriginal]);
    }

    public function url(ArchivoImagen $archivo): string
    {
        return '/storage/'.$archivo->ruta;
    }

    public function fueAlmacenada(string $nombreOriginal): bool
    {
        return array_key_exists($nombreOriginal, $this->almacenadas);
    }
}
