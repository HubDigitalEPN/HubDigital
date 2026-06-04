<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarTaxon;

final readonly class ActualizarTaxonInput
{
    public function __construct(
        public string $taxonId,
        public string $nombreCientifico,
        public ?string $autor = null,
        public ?int $anioDescripcion = null,
        public ?string $epitetoInfraespecifico = null,
    ) {}
}
