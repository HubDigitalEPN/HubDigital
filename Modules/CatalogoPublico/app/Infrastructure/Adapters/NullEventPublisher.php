<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Modules\CatalogoPublico\Application\Ports\EventPublisherPort;
use Modules\CatalogoPublico\Domain\Events\DomainEvent;

final class NullEventPublisher implements EventPublisherPort
{
    public function publish(DomainEvent $event): void {}
}
