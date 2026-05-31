<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class RecordatorioProximoAVencerMailable extends Mailable
{
    public function __construct(
        public readonly string $prestamoId,
        public readonly string $fechaLimite,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Recordatorio: tu préstamo vence el {$this->fechaLimite}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'gestionprestamosrecepciones::mails.recordatorio-proximo-a-vencer',
        );
    }
}
