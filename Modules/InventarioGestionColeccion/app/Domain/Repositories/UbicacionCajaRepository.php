<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Repositories;

use Modules\InventarioGestionColeccion\Domain\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\UbicacionCajaId;

interface UbicacionCajaRepository
{
    public function nextIdentity(): UbicacionCajaId;

    public function guardar(UbicacionCaja $ubicacion): void;

    public function buscarActivaPorCaja(CajaId $cajaId): ?UbicacionCaja;
}
