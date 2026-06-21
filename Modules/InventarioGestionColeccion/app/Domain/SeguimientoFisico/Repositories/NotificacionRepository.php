<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\NotificacionId;

/**
 * Contrato de persistencia para las notificaciones emitidas por el seguimiento físico.
 * Permite consultar la última notificación de una caja para evitar avisos duplicados.
 */
interface NotificacionRepository
{
    /** Genera el siguiente identificador único para una notificación nueva. */
    public function nextIdentity(): NotificacionId;

    /** Inserta o actualiza la notificación dada. */
    public function guardar(Notificacion $notificacion): void;

    /** Devuelve la notificación más reciente asociada a una caja, o null si no hay ninguna. */
    public function buscarUltimaNotificacionPorCaja(CajaId $cajaId): ?Notificacion;
}
