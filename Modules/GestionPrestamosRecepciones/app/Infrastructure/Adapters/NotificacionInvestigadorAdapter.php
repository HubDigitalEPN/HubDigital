<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;

/**
 * Adaptador de notificaciones al investigador. Resuelve el destinatario mediante
 * {@see InvestigadorEmailPort} y registra la notificación, devolviendo una referencia.
 */
final class NotificacionInvestigadorAdapter implements NotificacionInvestigadorPort
{
    public function __construct(
        private InvestigadorEmailPort $investigadorEmail,
    ) {}

    public function notificarCodigoQrDisponible(string $solicitudId, string $investigadorId, string $codigoQR): string
    {
        $email = $this->investigadorEmail->obtenerEmail($investigadorId);
        $referencia = (string) Str::uuid();

        Log::info('Notificación al investigador: Código QR disponible', [
            'referencia' => $referencia,
            'solicitud_id' => $solicitudId,
            'email' => $email,
            'codigo_qr' => $codigoQR,
        ]);

        return $referencia;
    }

    public function notificarRechazoSolicitud(string $solicitudId, string $investigadorId, string $comentario): string
    {
        $email = $this->investigadorEmail->obtenerEmail($investigadorId);
        $referencia = (string) Str::uuid();

        Log::info('Notificación al investigador: rechazo de solicitud', [
            'referencia' => $referencia,
            'solicitud_id' => $solicitudId,
            'email' => $email,
            'comentario' => $comentario,
        ]);

        return $referencia;
    }
}
