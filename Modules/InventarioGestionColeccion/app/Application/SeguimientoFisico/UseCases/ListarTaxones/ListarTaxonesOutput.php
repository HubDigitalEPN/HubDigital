<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxones;

final readonly class ListarTaxonesOutput
{
    /** @param ListarTaxonesItemOutput[] $items */
    public function __construct(public array $items) {}
}
