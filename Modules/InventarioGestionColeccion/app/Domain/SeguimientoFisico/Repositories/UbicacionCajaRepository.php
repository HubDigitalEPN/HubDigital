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

    /**
     * Retorna la ubicación cerrada (con retiradaEn) más reciente de la caja,
     * es decir, el último retiro registrado. Null si nunca ha sido retirada.
     */
    public function buscarUltimaRetiradaPorCaja(CajaId $cajaId): ?UbicacionCaja;
}
