<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ContextoEjecucionPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;

final class FakeContextoEjecucionAdapter implements ContextoEjecucionPort
{
    private ActorRol $actorRol = ActorRol::Sistema;

    private ?string $actorId = null;

    public function setActor(ActorRol $actorRol, ?string $actorId = null): void
    {
        $this->actorRol = $actorRol;
        $this->actorId = $actorId;
    }

    public function actorRol(): ActorRol
    {
        return $this->actorRol;
    }

    public function actorId(): ?string
    {
        return $this->actorId;
    }
}
