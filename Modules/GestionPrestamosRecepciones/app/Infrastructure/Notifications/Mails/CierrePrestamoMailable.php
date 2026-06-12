<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class CierrePrestamoMailable extends Mailable
{
    public function __construct(
        public readonly string $prestamoId,
        public readonly string $resultado,
        public readonly string $condicion,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Préstamo {$this->prestamoId} cerrado — Resultado: {$this->resultado}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'gestionprestamosrecepciones::mails.cierre-prestamo',
        );
    }
}
