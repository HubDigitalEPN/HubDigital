<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerMuestrasPorOldCode;

final readonly class ProponerMuestrasPorOldCodeInput
{
    public function __construct(
        public int $minimoEspecimenes = 1,
    ) {}
}
