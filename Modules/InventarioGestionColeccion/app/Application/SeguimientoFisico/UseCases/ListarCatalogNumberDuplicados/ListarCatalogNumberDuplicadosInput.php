<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCatalogNumberDuplicados;

final readonly class ListarCatalogNumberDuplicadosInput
{
    public function __construct(
        public int $minimoDuplicados = 2,
        public int $pagina = 1,
        public int $porPagina = 20,
    ) {}
}
