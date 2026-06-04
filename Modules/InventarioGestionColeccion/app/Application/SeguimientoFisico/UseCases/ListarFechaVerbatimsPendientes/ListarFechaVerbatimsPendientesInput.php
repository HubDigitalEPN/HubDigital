<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarFechaVerbatimsPendientes;

final readonly class ListarFechaVerbatimsPendientesInput
{
    public function __construct(
        public int $pagina = 1,
        public int $porPagina = 20,
    ) {}
}
