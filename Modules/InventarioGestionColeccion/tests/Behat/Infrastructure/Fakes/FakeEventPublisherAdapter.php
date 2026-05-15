<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\Fakes;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;

final class FakeEventPublisherAdapter implements EventPublisherPort
{
    /** @var object[] */
    private array $published = [];

    public function publish(object $evento): void
    {
        $this->published[] = $evento;
    }

    /** @return object[] */
    public function getPublished(): array
    {
        return $this->published;
    }
}
