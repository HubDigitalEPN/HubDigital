<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray;

final readonly class ConsultarContenidoUnitTrayInput
{
    public function __construct(
        public string $unitTrayId,
    ) {}
}
