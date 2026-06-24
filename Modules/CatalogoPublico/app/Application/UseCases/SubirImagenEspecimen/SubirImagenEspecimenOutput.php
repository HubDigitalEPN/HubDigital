<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\SubirImagenEspecimen;

final readonly class SubirImagenEspecimenOutput
{
    /**
     * @param  list<string>  $nivelesActualizados  claves "nivel:valor" que recibieron esta imagen como portada
     */
    private function __construct(
        public string $imagenId,
        public string $nombreArchivo,
        public string $occurrenceID,
        public string $autor,
        public array $nivelesActualizados,
    ) {}

    /**
     * @param  list<string>  $nivelesActualizados
     */
    public static function crear(
        string $imagenId,
        string $nombreArchivo,
        string $occurrenceID,
        string $autor,
        array $nivelesActualizados,
    ): self {
        return new self($imagenId, $nombreArchivo, $occurrenceID, $autor, $nivelesActualizados);
    }
}
