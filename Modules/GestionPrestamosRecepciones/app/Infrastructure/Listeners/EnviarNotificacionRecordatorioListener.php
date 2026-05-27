<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Illuminate\Support\Facades\Mail;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecordatorioDevolucionEnviado;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails\RecordatorioDevolucionMailable;

final class EnviarNotificacionRecordatorioListener
{
    public function __construct(
        private readonly InvestigadorEmailPort $investigadorEmail,
    ) {}

    public function handle(RecordatorioDevolucionEnviado $event): void
    {
        $email = $this->investigadorEmail->obtenerEmail($event->investigadorId);

        Mail::to($email)->send(new RecordatorioDevolucionMailable(
            estadoRecordatorio: $event->estadoRecordatorio->value,
            prestamoId: (string) $event->prestamoId,
        ));
    }
}
