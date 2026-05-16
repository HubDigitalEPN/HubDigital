<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;

class HttpSeguridadContextoAdapter implements ContextoEjecucionPort
{
    public function actorRol(): ActorRol
    {
        return ActorRol::from('esp32');
    }

    public function actorId(): ?string
    {
        return null;
    }
}
