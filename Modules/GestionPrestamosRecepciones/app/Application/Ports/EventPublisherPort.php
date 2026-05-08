<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

interface EventPublisherPort
{
    public function publish(object $event): void;
}
