<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\NotificacionId;

interface NotificacionRepository
{
    public function nextIdentity(): NotificacionId;

    public function guardar(Notificacion $notificacion): void;

    public function buscarUltimaNotificacionPorCaja(CajaId $cajaId): ?Notificacion;
}
