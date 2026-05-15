<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearCaja\CrearCajaOutput;

final readonly class ListarCajasOutput
{
    /** @param CrearCajaOutput[] $items */
    public function __construct(public array $items) {}
}
