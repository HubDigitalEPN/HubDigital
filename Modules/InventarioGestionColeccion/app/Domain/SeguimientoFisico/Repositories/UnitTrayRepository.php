<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

interface UnitTrayRepository
{
    public function nextIdentity(): UnitTrayId;

    public function guardar(UnitTray $unitTray): void;

    public function buscarPorId(UnitTrayId $id): ?UnitTray;

    /**
     * Retorna todos los UnitTrays asignados a una Caja.
     * Resultado vacío si la caja no tiene trays.
     *
     * @return UnitTray[]
     */
    public function buscarPorCaja(CajaId $cajaId): array;

    public function eliminar(UnitTrayId $id): void;
}
