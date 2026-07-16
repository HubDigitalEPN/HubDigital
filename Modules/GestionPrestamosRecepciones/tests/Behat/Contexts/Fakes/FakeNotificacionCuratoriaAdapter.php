<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes;

use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;

final class FakeNotificacionCuratoriaAdapter implements NotificacionCuratoriaPort
{
    private const CURADOR_ID = 'curador-fake-001';

    public function notificarIntervencionRequerida(string $solicitudId, string $investigadorId): string
    {
        return self::CURADOR_ID;
    }

    public function notificarNuevaSolicitudPorRevisar(string $solicitudId): string
    {
        return self::CURADOR_ID;
    }

    public function notificarDecisionDocumentalAOtrosCuradores(
        string $solicitudId,
        string $curadorQueDecideId,
        string $decision,
        ?string $motivo = null,
    ): string {
        return self::CURADOR_ID;
    }
}
