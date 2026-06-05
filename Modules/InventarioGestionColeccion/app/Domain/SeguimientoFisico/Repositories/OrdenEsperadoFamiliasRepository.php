<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\OrdenEsperadoFamilias;

interface OrdenEsperadoFamiliasRepository
{
    public function obtener(): OrdenEsperadoFamilias;

    public function guardar(OrdenEsperadoFamilias $orden): void;
}
