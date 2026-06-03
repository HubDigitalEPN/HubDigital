<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarFechaVerbatimsPendientes;

final readonly class ListarFechaVerbatimsPendientesOutput
{
    /** @param ListarFechaVerbatimsPendientesItem[] $items */
    public function __construct(
        public array $items,
        public int $totalVerbatimsDistintos,
        public int $pagina = 1,
        public int $porPagina = 20,
    ) {}

    public function totalPaginas(): int
    {
        return $this->totalVerbatimsDistintos === 0 ? 1 : (int) ceil($this->totalVerbatimsDistintos / $this->porPagina);
    }
}
