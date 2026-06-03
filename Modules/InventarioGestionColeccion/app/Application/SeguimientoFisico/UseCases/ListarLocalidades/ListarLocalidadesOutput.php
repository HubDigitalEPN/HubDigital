<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades;

final readonly class ListarLocalidadesOutput
{
    /** @param ListarLocalidadesItemOutput[] $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPaginas,
    ) {}
}
