<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

use Modules\CatalogoPublico\Domain\ValueObjects\ArchivoImagen;

interface AlmacenamientoImagenesPort
{
    public function guardar(string $contenido, string $nombreOriginal): ArchivoImagen;

    public function eliminar(ArchivoImagen $archivo): void;

    public function url(ArchivoImagen $archivo): string;
}
