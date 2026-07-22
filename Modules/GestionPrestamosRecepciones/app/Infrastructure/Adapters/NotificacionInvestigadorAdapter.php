<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionInvestigadorPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\ActaRecepcionFirmadaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\CodigoQrDisponibleNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\CorreccionCuratorialNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\OrdenAccionCorrectivaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\RecepcionConObservacionesNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\RecepcionFinalizadaNotification;
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

    public function notificarCorreccionesCuratoriales(
        string $solicitudId,
        string $investigadorId,
        array $correcciones,
    ): string {
        if ($correcciones === []) {
            return '';
        }

        $deposito = SolicitudDepositoEloquentModel::find($solicitudId);

        return $this->notificar($investigadorId, new CorreccionCuratorialNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
            correcciones: $correcciones,
        ), 'Correcciones de curaduría', $solicitudId);
    }

    public function notificarRecepcionFinalizada(string $solicitudId, string $investigadorId, string $estadoColeccion): string
    {
        $deposito = SolicitudDepositoEloquentModel::find($solicitudId);

        return $this->notificar($investigadorId, new RecepcionFinalizadaNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
            estadoColeccion: $estadoColeccion,
        ), 'Recepción finalizada', $solicitudId);
    }

    /**
     * @param  list<string>  $observaciones
     */
    public function notificarRecepcionConObservaciones(string $solicitudId, string $investigadorId, array $observaciones): string
    {
        $deposito = SolicitudDepositoEloquentModel::find($solicitudId);

        return $this->notificar($investigadorId, new RecepcionConObservacionesNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
            observaciones: $observaciones,
        ), 'Recepción con observaciones', $solicitudId);
    }

    public function notificarActaRecepcionDisponible(string $solicitudId, string $investigadorId): string
    {
        $deposito = SolicitudDepositoEloquentModel::find($solicitudId);

        return $this->notificar($investigadorId, new ActaRecepcionFirmadaNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
        ), 'Acta de recepción firmada disponible', $solicitudId);
    }

    public function notificarOrdenAccionCorrectiva(string $solicitudId, string $investigadorId, string $motivoFallo, string $accionCorrectiva): string
    {
        $deposito = SolicitudDepositoEloquentModel::find($solicitudId);

        return $this->notificar($investigadorId, new OrdenAccionCorrectivaNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
            motivoFallo: $motivoFallo,
            accionCorrectiva: $accionCorrectiva,
        ), 'Orden de acción correctiva', $solicitudId);
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
