<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;

class LaravelEventPublisherAdapter implements EventPublisherPort
{
    public function publish(object $evento): void
    {
        event($evento);
    }
}
