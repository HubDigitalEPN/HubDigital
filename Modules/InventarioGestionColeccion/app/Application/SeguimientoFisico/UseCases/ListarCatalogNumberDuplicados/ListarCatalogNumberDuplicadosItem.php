<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCatalogNumberDuplicados;

final readonly class ListarCatalogNumberDuplicadosItem
{
    /**
     * @param  array<int, array{id: string, codigoCatalogo: string, fechaColecta: string, colector: string}>  $especimenes
     */
    public function __construct(
        public string $catalogNumber,
        public int $total,
        public array $especimenes,
        public bool $fechasDistintas,
    ) {}
}
