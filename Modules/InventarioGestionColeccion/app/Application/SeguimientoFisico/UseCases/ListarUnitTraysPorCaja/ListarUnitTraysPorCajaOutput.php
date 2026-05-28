<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja;

final readonly class ListarUnitTraysPorCajaOutput
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(
        public array $items,
    ) {}
}
