<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCatalogNumberDuplicados;

final readonly class ListarCatalogNumberDuplicadosOutput
{
    /** @param ListarCatalogNumberDuplicadosItem[] $items */
    public function __construct(
        public array $items,
        public int $totalGrupos,
    ) {}
}
