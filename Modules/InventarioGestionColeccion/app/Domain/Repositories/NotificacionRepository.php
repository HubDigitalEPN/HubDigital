<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\Repositories;

use Modules\InventarioGestionColeccion\Domain\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\NotificacionId;

interface NotificacionRepository
{
    public function nextIdentity(): NotificacionId;

    public function guardar(Notificacion $notificacion): void;

    public function buscarUltimaNotificacionPorCaja(CajaId $cajaId): ?Notificacion;
}
