<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray;

/**
 * DTO de salida con un espécimen del unit tray para la tabla del mapa: código de
 * catálogo, nombre científico, género y especie (resueltos del árbol taxonómico),
 * número de individuos, provincia y localidad.
 */
final readonly class ConsultarContenidoUnitTrayItemOutput
{
    public function __construct(
        public string $especimenId,
        public string $codigoCatalogo,
        public ?string $nombreCientifico,
        public ?string $genero,
        public ?string $especie,
        public ?int $individualCount,
        public ?string $provincia,
        public ?string $localidad,
        public ?string $notas,
    ) {}
}
