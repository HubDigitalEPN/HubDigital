<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearRanuraGabinete\CrearRanuraGabineteOutput;

/**
 * DTO de salida con la lista de ranuras del gabinete.
 */
final readonly class ListarRanurasGabineteOutput
{
    /** @param CrearRanuraGabineteOutput[] $items */
    public function __construct(public array $items) {}
}
