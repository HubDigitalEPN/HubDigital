<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarIdentificacionesDeEspecimen;

final readonly class ListarIdentificacionesDeEspecimenOutput
{
    /** @param ListarIdentificacionesDeEspecimenItemOutput[] $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {}
}
