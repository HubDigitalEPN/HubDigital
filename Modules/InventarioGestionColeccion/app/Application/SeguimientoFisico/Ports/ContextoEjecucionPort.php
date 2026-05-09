<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;

interface ContextoEjecucionPort
{
    public function actorRol(): ActorRol;

    public function actorId(): ?string;
}
