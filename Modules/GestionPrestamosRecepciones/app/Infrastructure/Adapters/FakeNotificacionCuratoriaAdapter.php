<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;

/**
 * Adaptador de prueba para notificaciones a curaduría.
 *
 * Simula el envío de notificaciones de intervención requerida devolviendo
 * un identificador de curador estático.
 */
final class FakeNotificacionCuratoriaAdapter implements NotificacionCuratoriaPort
{
    private const CURADOR_ID = 'curador-fake-001';

    /**
     * Simula la notificación de intervención requerida.
     */
    public function notificarIntervencionRequerida(string $solicitudId, string $investigadorId): string
    {
        return self::CURADOR_ID;
    }

    /**
     * Simula la notificación de una nueva solicitud por revisar.
     */
    public function notificarNuevaSolicitudPorRevisar(string $solicitudId): string
    {
        return self::CURADOR_ID;
    }
}
