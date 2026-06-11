<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo electrónico para notificar que un préstamo está próximo a vencer.
 */
final class RecordatorioProximoAVencerMailable extends Mailable
{
    /**
     * Constructor del correo de recordatorio de préstamo próximo a vencer.
     *
     * @param string $prestamoId ID del préstamo asociado al recordatorio.
     * @param string $fechaLimite Fecha límite de devolución del préstamo.
     */
    public function __construct(
        public readonly string $prestamoId,
        public readonly string $fechaLimite,
    ) {}

    /**
     * Define el sobre del correo (asunto).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Recordatorio: tu préstamo vence el {$this->fechaLimite}",
        );
    }

    /**
     * Define el contenido del correo (vista).
     */
    public function content(): Content
    {
        return new Content(
            view: 'gestionprestamosrecepciones::mails.recordatorio-proximo-a-vencer',
        );
    }
}
