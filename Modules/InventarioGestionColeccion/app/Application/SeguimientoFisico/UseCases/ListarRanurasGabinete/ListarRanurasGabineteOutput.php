<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete\CrearRanuraGabineteOutput;

final readonly class ListarRanurasGabineteOutput
{
    /** @param CrearRanuraGabineteOutput[] $items */
    public function __construct(public array $items) {}
}
