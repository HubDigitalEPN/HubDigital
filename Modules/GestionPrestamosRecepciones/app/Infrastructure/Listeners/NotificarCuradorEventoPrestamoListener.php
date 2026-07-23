<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaSubida;
use Modules\GestionPrestamosRecepciones\Domain\Events\DevolucionRegistrada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaSolicitada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPrestamoEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\VerificacionEntregaRegistrada;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\AvisoPrestamoCuradorNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

/**
 * Avisa en la campana del portal a todos los curadores cuando el proceso de préstamo
 * llega a un hito que espera su acción.
 */
final class NotificarCuradorEventoPrestamoListener
{
    public function handle(
        SolicitudPrestamoEnviada|ActaFirmadaSubida|ProrrogaSolicitada|VerificacionEntregaRegistrada|DevolucionRegistrada $event,
    ): void {
        $curadores = User::where('rol', RolUsuario::CURADOR)->get();

        if ($curadores->isEmpty()) {
            return;
        }

        [$tipo, $mensaje, $url, $icono] = $this->aviso($event);

        Notification::send($curadores, new AvisoPrestamoCuradorNotification($tipo, $mensaje, $url, $icono));
    }

    /**
     * @return array{string, string, string, string} tipo, mensaje, url, icono
     */
    private function aviso(object $event): array
    {
        return match (true) {
            $event instanceof SolicitudPrestamoEnviada => [
                'prestamo_solicitud_enviada',
                sprintf('Nueva solicitud de préstamo por revisar (%s).', $this->codigoSolicitud((string) $event->solicitudId)),
                route('prestamos.curador.solicitud.revisar', (string) $event->solicitudId),
                'inbox-arrow-down',
            ],
            $event instanceof ActaFirmadaSubida => [
                'prestamo_acta_firmada_subida',
                sprintf('Se subió el acta firmada %s — pendiente de validación.', $this->codigoActa((string) $event->actaId)),
                route('prestamos.curador.acta.validar', (string) $event->actaId),
                'document-check',
            ],
            $event instanceof ProrrogaSolicitada => [
                'prestamo_prorroga_solicitada',
                sprintf('Se solicitó una prórroga para el préstamo %s.', $this->codigoPrestamo((string) $event->prestamoId)),
                route('prestamos.curador.prestamo.gestionar-prorroga', (string) $event->prestamoId),
                'calendar-days',
            ],
            $event instanceof VerificacionEntregaRegistrada => [
                'prestamo_verificacion_entrega',
                sprintf('El investigador registró la verificación de entrega del préstamo %s.', $this->codigoPrestamo((string) $event->prestamoId)),
                route('prestamos.curador.prestamo.aprobar-verificacion', (string) $event->prestamoId),
                'clipboard-document-check',
            ],
            $event instanceof DevolucionRegistrada => [
                'prestamo_devolucion_registrada',
                sprintf('Se registró la devolución del préstamo %s — pendiente de cierre.', $this->codigoPrestamo((string) $event->prestamoId)),
                route('prestamos.curador.prestamo.cerrar', (string) $event->prestamoId),
                'arrow-uturn-left',
            ],
        };
    }

    private function codigoSolicitud(string $id): string
    {
        return SolicitudPrestamoModel::find($id)?->codigo ?? $id;
    }

    private function codigoActa(string $id): string
    {
        return ActaPrestamoModel::find($id)?->codigo ?? $id;
    }

    private function codigoPrestamo(string $id): string
    {
        $prestamo = PrestamoEloquentModel::with('acta')->find($id);

        return $prestamo?->codigo ?? $prestamo?->acta?->codigo ?? $id;
    }
}
