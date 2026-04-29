<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Repositories;

use Modules\InventarioGestionColeccion\Domain\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CodigoRfid;

interface CajaRepository
{
    public function nextIdentity(): CajaId;

    public function guardar(Caja $caja): void;

    public function buscarPorId(CajaId $id): ?Caja;

    public function buscarPorCodigo(CodigoCaja $codigo): ?Caja;

    public function buscarPorCodigoRfid(CodigoRfid $rfid): ?Caja;
}
