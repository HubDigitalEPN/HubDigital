<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCatalogNumberDuplicados;

final readonly class ListarCatalogNumberDuplicadosOutput
{
    /** @param ListarCatalogNumberDuplicadosItem[] $items */
    public function __construct(
        public array $items,
        public int $totalGrupos,
        public int $pagina = 1,
        public int $porPagina = 20,
    ) {}

    public function totalPaginas(): int
    {
        return $this->totalGrupos === 0 ? 1 : (int) ceil($this->totalGrupos / $this->porPagina);
    }
}
