<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;

final class FakeNotificacionCuratoriaAdapter implements NotificacionCuratoriaPort
{
    private const CURADOR_ID = 'curador-fake-001';

    public function notificarIntervencionRequerida(string $solicitudId, string $investigadorId): string
    {
        return self::CURADOR_ID;
    }
}
