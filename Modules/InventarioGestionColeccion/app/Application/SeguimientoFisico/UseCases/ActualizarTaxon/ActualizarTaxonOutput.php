<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarTaxon;

final readonly class ActualizarTaxonOutput
{
    public function __construct(
        public string $id,
        public string $nombreCientifico,
        public string $rango,
        public ?string $autor,
        public ?int $anioDescripcion,
        public string $estado,
        public ?string $epitetoInfraespecifico = null,
    ) {}
}
