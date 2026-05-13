<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;

interface GeneradorActaPdfPort
{
    /** @param Especimen[] $especimenes */
    public function generar(EntidadDepositante $entidad, array $especimenes): string;
}
