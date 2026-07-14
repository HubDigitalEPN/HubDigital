<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class DevolucionRegistradaMailable extends Mailable
{
    public function __construct(
        public readonly string $numeroPrestamo,
        public readonly string $investigadorNombre,
        public readonly string $prestamoUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Préstamo {$this->numeroPrestamo} — devolución registrada",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'gestionprestamosrecepciones::mails.devolucion-registrada',
        );
    }
}
