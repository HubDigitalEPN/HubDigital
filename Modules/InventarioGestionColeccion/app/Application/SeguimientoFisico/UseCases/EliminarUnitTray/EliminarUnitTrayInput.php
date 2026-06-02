<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarUnitTray;

final readonly class EliminarUnitTrayInput
{
    public function __construct(
        public string $unitTrayId,
    ) {}
}
