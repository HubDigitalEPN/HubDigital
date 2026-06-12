<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Illuminate\Support\Facades\Mail;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoCerrado;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails\CierrePrestamoMailable;

final class EnviarNotificacionCierrePrestamoListener
{
    public function __construct(
        private readonly InvestigadorEmailPort $investigadorEmail,
    ) {}

    public function handle(PrestamoCerrado $event): void
    {
        $email = $this->investigadorEmail->obtenerEmail($event->investigadorId);

        Mail::to($email)->send(new CierrePrestamoMailable(
            prestamoId: (string) $event->prestamoId,
            resultado: $event->resultado->value,
            condicion: $event->condicionEspecimen->value,
        ));
    }
}
