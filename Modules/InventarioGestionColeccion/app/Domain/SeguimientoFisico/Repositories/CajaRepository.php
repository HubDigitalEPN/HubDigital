<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;

interface CajaRepository
{
    public function nextIdentity(): CajaId;

    public function guardar(Caja $caja): void;

    public function buscarPorId(CajaId $id): ?Caja;

    public function buscarPorCodigo(CodigoCaja $codigo): ?Caja;

    public function buscarPorCodigoRfid(CodigoRfid $rfid): ?Caja;
}
