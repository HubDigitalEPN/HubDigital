<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Notificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\NotificacionId;

final class InMemoryNotificacionRepository implements NotificacionRepository
{
    /** @var array<string, Notificacion> */
    private array $store = [];

    public function nextIdentity(): NotificacionId
    {
        return NotificacionId::generar();
    }

    public function guardar(Notificacion $notificacion): void
    {
        $this->store[(string) $notificacion->id()] = $notificacion;
    }

    public function buscarUltimaNotificacionPorCaja(CajaId $cajaId): ?Notificacion
    {
        $matches = array_filter(
            $this->store,
            fn (Notificacion $n) => $n->cajaId()->equals($cajaId),
        );

        return empty($matches) ? null : end($matches);
    }
}
