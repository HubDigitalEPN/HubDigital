<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearGabinete\CrearGabineteOutput;

final readonly class ListarGabineteOutput
{
    /** @param CrearGabineteOutput[] $items */
    public function __construct(public array $items) {}
}
