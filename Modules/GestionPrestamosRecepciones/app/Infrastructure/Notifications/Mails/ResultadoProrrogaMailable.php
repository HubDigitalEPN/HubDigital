<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo que notifica al investigador el resultado de su solicitud de prórroga.
 *
 * El campo {@see $resultado} toma los valores 'aprobada' o 'rechazada'.
 */
final class ResultadoProrrogaMailable extends Mailable
{
    public function __construct(
        public readonly string $numeroPrestamo,
        public readonly string $investigadorNombre,
        public readonly string $resultado,
        public readonly ?string $nuevaFechaFin = null,
        public readonly ?string $comentario = null,
    ) {}

    public function envelope(): Envelope
    {
        $estado = $this->resultado === 'aprobada' ? 'aprobada' : 'rechazada';

        return new Envelope(
            subject: "Tu solicitud de prórroga del préstamo {$this->numeroPrestamo} fue {$estado}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'gestionprestamosrecepciones::mails.resultado-prorroga',
        );
    }
}
