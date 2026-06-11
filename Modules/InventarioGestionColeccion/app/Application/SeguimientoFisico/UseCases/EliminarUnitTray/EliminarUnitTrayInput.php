<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarUnitTray;

/**
 * DTO de entrada con el unit tray que se quiere eliminar.
 */
final readonly class EliminarUnitTrayInput
{
    public function __construct(
        public string $unitTrayId,
    ) {}
}
