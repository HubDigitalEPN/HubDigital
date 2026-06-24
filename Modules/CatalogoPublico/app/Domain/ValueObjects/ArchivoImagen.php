<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final readonly class ArchivoImagen
{
    private function __construct(
        public string $nombreOriginal,
        public string $ruta,
        public string $disco,
    ) {}

    public static function crear(string $nombreOriginal, string $ruta, string $disco): self
    {
        if (trim($nombreOriginal) === '') {
            throw new \InvalidArgumentException('El nombre original del archivo no puede ser vacío');
        }

        if (trim($ruta) === '') {
            throw new \InvalidArgumentException('La ruta de almacenamiento del archivo no puede ser vacía');
        }

        return new self(trim($nombreOriginal), trim($ruta), trim($disco));
    }
}
