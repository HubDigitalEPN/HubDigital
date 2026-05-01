<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UbicacionCajaId;

interface UbicacionCajaRepository
{
    public function nextIdentity(): UbicacionCajaId;

    public function guardar(UbicacionCaja $ubicacion): void;

    public function buscarActivaPorCaja(CajaId $cajaId): ?UbicacionCaja;
}
