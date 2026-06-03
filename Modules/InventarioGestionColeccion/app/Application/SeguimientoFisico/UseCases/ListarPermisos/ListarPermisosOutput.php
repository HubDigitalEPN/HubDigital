<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarPermisos;

final readonly class ListarPermisosOutput
{
    /** @param ListarPermisosItemOutput[] $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {}
}
