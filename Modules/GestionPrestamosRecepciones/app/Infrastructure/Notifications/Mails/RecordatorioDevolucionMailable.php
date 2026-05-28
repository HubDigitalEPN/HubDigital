<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class RecordatorioDevolucionMailable extends Mailable
{
    public function __construct(
        public readonly string $estadoRecordatorio,
        public readonly string $prestamoId,
        public readonly string $fechaLimite,
    ) {}

    public function envelope(): Envelope
    {
        $asunto = match ($this->estadoRecordatorio) {
            'próximo a vencer' => 'Recordatorio: su préstamo está próximo a vencer',
            'vencido' => 'Aviso: su préstamo ha vencido',
            default => 'Aviso sobre su préstamo',
        };

        return new Envelope(subject: $asunto);
    }

    public function content(): Content
    {
        return new Content(
            view: 'gestionprestamosrecepciones::mails.recordatorio-devolucion',
        );
    }
}
