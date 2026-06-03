<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes;

final readonly class ListarLocalidadVerbatimsPendientesOutput
{
    /** @param ListarLocalidadVerbatimsPendientesItem[] $items */
    public function __construct(
        public array $items,
        public int $totalVerbatimsDistintos,
    ) {}
}
