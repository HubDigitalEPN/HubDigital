<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\DomainEvent;

final class SyncEventPublisherAdapter implements EventPublisherPort
{
    public function publish(DomainEvent $event): void
    {
        Log::info('[DomainEvent] '.$event->nombreEvento(), [
            'ocurrido_en' => $event->ocurridoEn()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
