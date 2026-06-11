<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo electrónico para notificar que un préstamo ya se encuentra vencido.
 */
final class RecordatorioVencidoMailable extends Mailable
{
    /**
     * Constructor del correo de aviso de préstamo vencido.
     *
     * @param string $prestamoId ID del préstamo asociado.
     * @param string $fechaLimite Fecha en la que venció el préstamo.
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
            subject: '⚠ Préstamo vencido — Se requiere acción inmediata',
        );
    }

    /**
     * Define el contenido del correo (vista).
     */
    public function content(): Content
    {
        return new Content(
            view: 'gestionprestamosrecepciones::mails.recordatorio-vencido',
        );
    }
}
