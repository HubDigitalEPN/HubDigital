<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

use Modules\CatalogoPublico\Domain\Events\DomainEvent;

interface EventPublisherPort
{
    public function publish(DomainEvent $event): void;
}
