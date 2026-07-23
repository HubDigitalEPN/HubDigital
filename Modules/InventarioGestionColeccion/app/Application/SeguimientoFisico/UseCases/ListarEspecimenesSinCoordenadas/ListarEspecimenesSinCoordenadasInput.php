<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesSinCoordenadas;

final readonly class ListarEspecimenesSinCoordenadasInput
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
