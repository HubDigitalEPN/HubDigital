<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;

final class LaravelEventPublisherAdapter implements EventPublisherPort
{
    public function publish(object $event): void
    {
        event($event);
    }
}
