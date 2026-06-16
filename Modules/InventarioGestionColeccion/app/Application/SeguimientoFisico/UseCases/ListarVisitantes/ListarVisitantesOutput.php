<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarVisitantes;

final readonly class ListarVisitantesOutput
{
    /**
     * @param  VisitanteResumen[]  $items
     */
    public function __construct(
        public array $items,
    ) {}
}
