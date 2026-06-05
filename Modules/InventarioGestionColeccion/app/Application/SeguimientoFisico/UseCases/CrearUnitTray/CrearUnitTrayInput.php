<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearUnitTray;

final readonly class CrearUnitTrayInput
{
    /**
     * @param  string[]  $especimenIds  UUIDs de los especímenes que se asignan al tray
     */
    public function __construct(
        public string $cajaId,
        public array $especimenIds = [],
    ) {}
}
