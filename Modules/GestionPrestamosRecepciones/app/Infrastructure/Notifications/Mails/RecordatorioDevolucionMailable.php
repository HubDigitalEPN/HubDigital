<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo electrónico de recordatorio de devolución de préstamo.
 */
final class RecordatorioDevolucionMailable extends Mailable
{
    /**
     * Constructor del correo de recordatorio de devolución.
     *
     * @param string $estadoRecordatorio Estado actual del recordatorio (ej. 'próximo a vencer', 'vencido').
     * @param string $prestamoId ID del préstamo asociado al recordatorio.
     * @param string $fechaLimite Fecha límite de devolución del préstamo.
     */
    public function __construct(
        public readonly string $estadoRecordatorio,
        public readonly string $prestamoId,
        public readonly string $fechaLimite,
    ) {}

    /**
     * Define el sobre del correo (asunto).
     */
    public function envelope(): Envelope
    {
        $asunto = match ($this->estadoRecordatorio) {
            'próximo a vencer' => 'Recordatorio: su préstamo está próximo a vencer',
            'vencido' => 'Aviso: su préstamo ha vencido',
            default => 'Aviso sobre su préstamo',
        };

        return new Envelope(subject: $asunto);
    }

    /**
     * Define el contenido del correo (vista).
     */
    public function content(): Content
    {
        return new Content(
            view: 'gestionprestamosrecepciones::mails.recordatorio-devolucion',
        );
    }
}
