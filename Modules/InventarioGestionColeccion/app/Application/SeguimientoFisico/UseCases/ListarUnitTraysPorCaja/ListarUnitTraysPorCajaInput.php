<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarUnitTraysPorCaja;

final readonly class ListarUnitTraysPorCajaInput
{
    public function __construct(
        public string $cajaId,
    ) {}
}
