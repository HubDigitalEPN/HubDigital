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

    public function guardar(string $contenido, string $nombreDeseado): ArchivoImagen
    {
        $nombreFinal = $this->resolverColision($nombreDeseado);
        $this->almacenadas[$nombreFinal] = $contenido;

        return ArchivoImagen::crear(
            nombreOriginal: $nombreFinal,
            ruta: 'divulgacion/imagenes/'.$nombreFinal,
            disco: 'public',
        );
    }

    private function resolverColision(string $nombre): string
    {
        if (! array_key_exists($nombre, $this->almacenadas)) {
            return $nombre;
        }

        $info = pathinfo($nombre);
        $base = $info['filename'];
        $ext = isset($info['extension']) ? '.'.$info['extension'] : '';
        $n = 2;

        do {
            $candidato = $base.'_'.$n.$ext;
            $n++;
        } while (array_key_exists($candidato, $this->almacenadas));

        return $candidato;
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
