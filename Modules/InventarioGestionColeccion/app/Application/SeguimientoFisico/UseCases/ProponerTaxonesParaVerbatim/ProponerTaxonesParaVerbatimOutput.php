<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerTaxonesParaVerbatim;

final readonly class ProponerTaxonesParaVerbatimOutput
{
    /** @param ProponerTaxonesParaVerbatimItem[] $items */
    public function __construct(
        public array $items,
        public string $verbatimConsultado,
    ) {}
}
