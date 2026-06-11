<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja;

/**
 * DTO de salida con la lista de unit trays de la caja consultada.
 */
final readonly class ListarUnitTraysPorCajaOutput
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(
        public array $items,
    ) {}
}
