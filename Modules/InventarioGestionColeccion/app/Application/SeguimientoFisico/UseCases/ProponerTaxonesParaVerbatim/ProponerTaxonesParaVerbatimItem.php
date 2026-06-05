<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerTaxonesParaVerbatim;

final readonly class ProponerTaxonesParaVerbatimItem
{
    public function __construct(
        public string $id,
        public string $nombreCientifico,
        public string $rango,
        public float $puntajeSimilitud,
    ) {}
}
