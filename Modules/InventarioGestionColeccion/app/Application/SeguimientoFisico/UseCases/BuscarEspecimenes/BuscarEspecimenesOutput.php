<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes;

final readonly class BuscarEspecimenesOutput
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(
        public array $items,
        public int $total = 0,
        public int $page = 1,
        public int $perPage = 25,
        public int $totalPaginas = 1,
    ) {}
}
