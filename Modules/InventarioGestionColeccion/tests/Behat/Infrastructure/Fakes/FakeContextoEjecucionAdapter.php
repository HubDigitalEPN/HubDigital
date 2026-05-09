<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;

final class FakeContextoEjecucionAdapter implements ContextoEjecucionPort
{
    public function actorRol(): ActorRol
    {
        return ActorRol::Sistema;
    }

    public function actorId(): ?string
    {
        return null;
    }
}
