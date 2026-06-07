<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray;

final readonly class ConsultarContenidoUnitTrayOutput
{
    /** @param ConsultarContenidoUnitTrayItemOutput[] $items */
    public function __construct(
        public array $items,
    ) {}
}
