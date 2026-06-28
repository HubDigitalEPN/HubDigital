<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\CodigoQrDisponibleNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\SolicitudRechazadaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Adaptador de notificaciones al depositante. Entrega por correo y por el portal
 * (campana) usando las notificaciones de Laravel sobre el modelo User.
 */
final class NotificacionInvestigadorAdapter implements NotificacionInvestigadorPort
{
    public function notificarCodigoQrDisponible(string $solicitudId, string $investigadorId, string $codigoQR): string
    {
        $deposito = SolicitudDepositoEloquentModel::find($solicitudId);

        return $this->notificar($investigadorId, new CodigoQrDisponibleNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
            codigoQR: $codigoQR,
            tipoTramite: $deposito?->tipo_tramite ?? 'Depósito',
        ), 'Código QR disponible', $solicitudId);
    }

    public function notificarRechazoSolicitud(string $solicitudId, string $investigadorId, string $comentario): string
    {
        $deposito = SolicitudDepositoEloquentModel::find($solicitudId);

        return $this->notificar($investigadorId, new SolicitudRechazadaNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
            comentario: $comentario,
        ), 'Rechazo de solicitud', $solicitudId);
    }

    /**
     * Entrega la notificación al depositante de forma defensiva: si el usuario no
     * existe (p. ej. en pruebas), registra el evento y devuelve igualmente una
     * referencia no vacía para preservar el contrato del puerto.
     */
    private function notificar(string $investigadorId, object $notificacion, string $contexto, string $solicitudId): string
    {
        $referencia = (string) Str::uuid();
        $usuario = User::find($investigadorId);

        if ($usuario === null) {
            Log::info('Notificación al investigador omitida: usuario no encontrado', [
                'referencia' => $referencia, 'contexto' => $contexto,
                'solicitud_id' => $solicitudId, 'investigador_id' => $investigadorId,
            ]);

            return $referencia;
        }

        $usuario->notify($notificacion);

        return $referencia;
    }
}
