<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecordatorioDevolucionEnviado;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecordatorio;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails\RecordatorioProximoAVencerMailable;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails\RecordatorioVencidoMailable;

/**
 * Suscriptor de eventos para el envío de recordatorios de devolución.
 *
 * Escucha el evento {@see RecordatorioDevolucionEnviado} y despacha el correo
 * electrónico correspondiente (próximo a vencer o vencido) al investigador.
 */
final class EnviarNotificacionRecordatorioListener
{
    /**
     * Constructor del listener para el envío de notificaciones de recordatorio.
     *
     * @param InvestigadorEmailPort $investigadorEmail Puerto para obtener el email del investigador.
     * @param PrestamoRepositoryInterface $prestamoRepo Repositorio de préstamos.
     */
    public function __construct(
        private readonly InvestigadorEmailPort $investigadorEmail,
        private readonly PrestamoRepositoryInterface $prestamoRepo,
    ) {}

    /**
     * Maneja el evento de dominio y envía el correo.
     */
    public function handle(RecordatorioDevolucionEnviado $event): void
    {
        $prestamo = $this->prestamoRepo->buscarPorId($event->prestamoId);

        if ($prestamo === null) {
            return;
        }

        $fechaLimite = Carbon::instance($prestamo->fechaFin())
            ->locale('es')
            ->isoFormat('D [de] MMMM [de] YYYY');

        $email = $this->investigadorEmail->obtenerEmail($event->investigadorId);

        Mail::to($email)->send($this->resolverMailable($event->estadoRecordatorio, (string) $event->prestamoId, $fechaLimite));
    }

    /**
     * Resuelve qué clase Mailable instanciar según el estado del recordatorio.
     */
    private function resolverMailable(EstadoRecordatorio $estado, string $prestamoId, string $fechaLimite): Mailable
    {
        return match ($estado) {
            EstadoRecordatorio::ProximoAVencer => new RecordatorioProximoAVencerMailable(
                prestamoId: $prestamoId,
                fechaLimite: $fechaLimite,
            ),
            EstadoRecordatorio::Vencido => new RecordatorioVencidoMailable(
                prestamoId: $prestamoId,
                fechaLimite: $fechaLimite,
            ),
        };
    }
}
