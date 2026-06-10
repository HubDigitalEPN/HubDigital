<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;

/**
 * Adaptador para publicar eventos de dominio utilizando el sistema de eventos de Laravel.
 *
 * Implementa {@see EventPublisherPort} delegando la publicación a la función global `event()`.
 */
final class LaravelEventPublisherAdapter implements EventPublisherPort
{
    /**
     * Publica un evento de dominio.
     */
    public function publish(object $event): void
    {
        event($event);
    }
}
