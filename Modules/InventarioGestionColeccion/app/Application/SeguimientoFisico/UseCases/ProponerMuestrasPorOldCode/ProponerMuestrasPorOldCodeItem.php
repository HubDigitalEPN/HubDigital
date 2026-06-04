<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerMuestrasPorOldCode;

final readonly class ProponerMuestrasPorOldCodeItem
{
    /** @param string[] $especimenIds */
    public function __construct(
        public string $oldCode,
        public int $totalEspecimenes,
        public array $especimenIds,
        public ?string $colectorComun,
        public ?string $localidadVerbatimComun,
    ) {}
}
