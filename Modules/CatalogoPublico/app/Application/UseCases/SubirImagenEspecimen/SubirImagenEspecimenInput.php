<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\SubirImagenEspecimen;

final readonly class SubirImagenEspecimenInput
{
    public function __construct(
        public string $occurrenceID,
        public string $nombreArchivo,
        public string $contenido,
        public string $nombreAutor,
        public string $apellidoAutor,
    ) {}
}
