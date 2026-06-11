<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\DomainEvent;

/**
 * Adaptador para publicar eventos de dominio de forma síncrona en el log.
 *
 * Implementa {@see EventPublisherPort}. Utilizado principalmente para depuración
 * o en entornos donde no se requiere un bus de eventos distribuido.
 */
final class SyncEventPublisherAdapter implements EventPublisherPort
{
    /**
     * Registra el evento en los logs del sistema.
     */
    public function publish(DomainEvent $event): void
    {
        Log::info('[DomainEvent] '.$event->nombreEvento(), [
            'ocurrido_en' => $event->ocurridoEn()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
