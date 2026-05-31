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

final class EnviarNotificacionRecordatorioListener
{
    public function __construct(
        private readonly InvestigadorEmailPort $investigadorEmail,
        private readonly PrestamoRepositoryInterface $prestamoRepo,
    ) {}

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
