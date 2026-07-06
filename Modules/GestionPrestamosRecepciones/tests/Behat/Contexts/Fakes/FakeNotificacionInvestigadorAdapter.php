<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Fakes;

use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;

final class FakeNotificacionInvestigadorAdapter implements NotificacionInvestigadorPort
{
    private const REFERENCIA = 'notif-investigador-fake-001';

    public function notificarCodigoQrDisponible(string $solicitudId, string $investigadorId, string $codigoQR): string
    {
        return self::REFERENCIA;
    }

    public function notificarRechazoSolicitud(string $solicitudId, string $investigadorId, string $comentario): string
    {
        return self::REFERENCIA;
    }

    public function notificarRecepcionFinalizada(string $solicitudId, string $investigadorId, string $estadoColeccion): string
    {
        return self::REFERENCIA;
    }

    /**
     * @param  list<string>  $observaciones
     */
    public function notificarRecepcionConObservaciones(string $solicitudId, string $investigadorId, array $observaciones): string
    {
        return self::REFERENCIA;
    }

    public function notificarOrdenAccionCorrectiva(string $solicitudId, string $investigadorId, string $motivoFallo, string $accionCorrectiva): string
    {
        return self::REFERENCIA;
    }
}
