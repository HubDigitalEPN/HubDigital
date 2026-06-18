<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ExportarRegistrosEspecimenes;

final readonly class ExportarRegistrosEspecimenesOutput
{
    private function __construct(
        public readonly string $nombreArchivo,
        public readonly string $contenidoXlsx,
    ) {}

    public static function fromPrimitives(string $nombreArchivo, string $contenidoXlsx): self
    {
        return new self($nombreArchivo, $contenidoXlsx);
    }
}
