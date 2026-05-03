<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Domain\Events\DomainEvent;

interface EventPublisherPort
{
    public function publish(DomainEvent $event): void;
}
