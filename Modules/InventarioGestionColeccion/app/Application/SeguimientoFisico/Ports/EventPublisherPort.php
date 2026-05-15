<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

interface EventPublisherPort
{
    public function publish(object $evento): void;
}
