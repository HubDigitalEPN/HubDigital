<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UbicacionCajaId;

/**
 * Contrato de persistencia para las ubicaciones de caja, el historial de estancias
 * de una caja en una ranura. Una ubicación "activa" (sin fecha de retirada) representa
 * la presencia vigente; las cerradas conforman el rastro histórico de movimientos.
 */
interface UbicacionCajaRepository
{
    /** Genera el siguiente identificador único para una ubicación nueva. */
    public function nextIdentity(): UbicacionCajaId;

    /** Inserta o actualiza la ubicación dada. */
    public function guardar(UbicacionCaja $ubicacion): void;

    /** Devuelve la ubicación activa (sin retirada) de una caja, o null si no está ubicada. */
    public function buscarActivaPorCaja(CajaId $cajaId): ?UbicacionCaja;

    /**
     * Retorna la ubicación cerrada (con retiradaEn) más reciente de la caja,
     * es decir, el último retiro registrado. Null si nunca ha sido retirada.
     */
    public function buscarUltimaRetiradaPorCaja(CajaId $cajaId): ?UbicacionCaja;
}
