<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ReubicarUnitTray;

/**
 * DTO de entrada para reubicar un unit tray completo a otra caja.
 */
final readonly class ReubicarUnitTrayInput
{
    public function __construct(
        public string $unitTrayId,
        public string $cajaDestinoId,
    ) {}
}
