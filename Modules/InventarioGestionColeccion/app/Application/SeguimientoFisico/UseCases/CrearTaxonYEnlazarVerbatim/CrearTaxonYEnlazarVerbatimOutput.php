<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearTaxonYEnlazarVerbatim;

final readonly class CrearTaxonYEnlazarVerbatimOutput
{
    public function __construct(
        public string $taxonId,
        public string $nombreCientifico,
        public int $especimenesEnlazados,
    ) {}
}
