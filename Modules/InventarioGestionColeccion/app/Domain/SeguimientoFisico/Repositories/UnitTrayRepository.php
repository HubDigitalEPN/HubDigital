<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

interface UnitTrayRepository
{
    public function nextIdentity(): UnitTrayId;

    /**
     * Siguiente número correlativo disponible para un UnitTray dentro de la Caja.
     * Retorna 1 si la caja aún no tiene trays. El número es un identificador
     * autogenerado: el orden de presentación lo decide la taxonomía, no este valor.
     */
    public function siguienteNumero(CajaId $cajaId): int;

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
