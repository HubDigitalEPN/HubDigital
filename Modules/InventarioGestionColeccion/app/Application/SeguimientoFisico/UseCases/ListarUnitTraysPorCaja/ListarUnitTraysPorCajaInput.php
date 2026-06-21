<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja;

/**
 * DTO de entrada con la caja cuyos unit trays se quieren listar.
 */
final readonly class ListarUnitTraysPorCajaInput
{
    public function __construct(
        public string $cajaId,
    ) {}
}
