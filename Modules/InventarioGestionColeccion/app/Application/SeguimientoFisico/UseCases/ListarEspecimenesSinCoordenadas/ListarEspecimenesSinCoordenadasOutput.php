<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesSinCoordenadas;

final readonly class ListarEspecimenesSinCoordenadasOutput
{
    /**
     * @param  list<array<string, mixed>>  $items  Cada ítem incluye todas las columnas
     *                                             del espécimen + `sinLocalidad` (bool).
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPaginas,
    ) {}
}
