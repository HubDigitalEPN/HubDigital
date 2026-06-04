<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerTaxonesParaVerbatim;

final readonly class ProponerTaxonesParaVerbatimInput
{
    public function __construct(
        public string $verbatim,
        public int $limite = 10,
    ) {}
}
