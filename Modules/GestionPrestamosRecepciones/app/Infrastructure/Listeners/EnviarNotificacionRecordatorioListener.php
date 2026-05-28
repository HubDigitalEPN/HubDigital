<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecordatorioDevolucionEnviado;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails\RecordatorioDevolucionMailable;

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

        Mail::to($email)->send(new RecordatorioDevolucionMailable(
            estadoRecordatorio: $event->estadoRecordatorio->value,
            prestamoId: (string) $event->prestamoId,
            fechaLimite: $fechaLimite,
        ));
    }
}
