<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarMuestrasParaRevision;

final readonly class ListarMuestrasParaRevisionInput
{
    public function __construct(
        public int $pagina = 1,
        public int $porPagina = 25,
    ) {}
}
