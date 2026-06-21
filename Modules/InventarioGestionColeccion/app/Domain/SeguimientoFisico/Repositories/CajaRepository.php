<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;

/**
 * Contrato de persistencia para las cajas entomológicas. Además del acceso por
 * identidad ofrece búsquedas por código legible y por tag RFID, que es como el
 * seguimiento físico resuelve las lecturas del ESP32 hasta la caja correspondiente.
 */
interface CajaRepository
{
    /** Genera el siguiente identificador único para una caja nueva. */
    public function nextIdentity(): CajaId;

    /** Inserta o actualiza la caja dada. */
    public function guardar(Caja $caja): void;

    /** Recupera una caja por su identificador, o null si no existe. */
    public function buscarPorId(CajaId $id): ?Caja;

    /** Busca una caja por su código legible por humanos, o null si no existe. */
    public function buscarPorCodigo(CodigoCaja $codigo): ?Caja;

    /** Resuelve la caja asociada a un tag RFID/NFC, o null si ese UID no está asignado. */
    public function buscarPorCodigoRfid(CodigoRfid $rfid): ?Caja;

    /**
     * Lista todas las cajas registradas.
     *
     * @return Caja[]
     */
    public function buscarTodas(): array;

    /** Elimina la caja indicada de forma permanente. */
    public function eliminar(CajaId $id): void;
}
