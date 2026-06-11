<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UnitTray;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;

/**
 * Contrato de persistencia para los UnitTrays, las bandejas internas que subdividen
 * una caja. Ofrece el cálculo del siguiente número correlativo dentro de la caja y el
 * acceso a los trays de una caja para componer su contenido.
 */
interface UnitTrayRepository
{
    /** Genera el siguiente identificador único para un UnitTray nuevo. */
    public function nextIdentity(): UnitTrayId;

    /**
     * Siguiente número correlativo disponible para un UnitTray dentro de la Caja.
     * Retorna 1 si la caja aún no tiene trays. El número es un identificador
     * autogenerado: el orden de presentación lo decide la taxonomía, no este valor.
     */
    public function siguienteNumero(CajaId $cajaId): int;

    /** Inserta o actualiza el UnitTray dado. */
    public function guardar(UnitTray $unitTray): void;

    /** Recupera un UnitTray por su identificador, o null si no existe. */
    public function buscarPorId(UnitTrayId $id): ?UnitTray;

    /**
     * Retorna todos los UnitTrays asignados a una Caja.
     * Resultado vacío si la caja no tiene trays.
     *
     * @return UnitTray[]
     */
    public function buscarPorCaja(CajaId $cajaId): array;

    /** Elimina el UnitTray indicado de forma permanente. */
    public function eliminar(UnitTrayId $id): void;
}
