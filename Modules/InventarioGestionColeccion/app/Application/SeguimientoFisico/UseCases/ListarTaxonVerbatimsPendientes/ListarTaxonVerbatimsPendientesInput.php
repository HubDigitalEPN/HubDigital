<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxonVerbatimsPendientes;

final readonly class ListarTaxonVerbatimsPendientesInput
{
    public function __construct(
        public int $limiteCandidatosPorVerbatim = 5,
        public int $pagina = 1,
        public int $porPagina = 20,
    ) {}
}
