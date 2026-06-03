<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerMuestrasPorOldCode;

final readonly class ProponerMuestrasPorOldCodeOutput
{
    /** @param ProponerMuestrasPorOldCodeItem[] $items */
    public function __construct(
        public array $items,
        public int $totalGrupos,
    ) {}
}
