<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa al depositante de que curaduría corrigió un dato de formato de su matriz.
 *
 * El depositante firmó una declaración al enviarla, así que un cambio posterior no
 * puede quedar en silencio aunque sea menor y le evite tener que reenviar todo.
 */
final class CorreccionCuratorialNotification extends Notification
{
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly string $campo,
        public readonly ?string $valorAnterior,
        public readonly ?string $valorNuevo,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Curaduría ajustó un dato de tu matriz')
            ->view('gestionprestamosrecepciones::mails.correccion-curatorial', [
                'numero' => $this->numero,
                'campo' => $this->campo,
                'valorAnterior' => $this->valorAnterior,
                'valorNuevo' => $this->valorNuevo,
                'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'correccion_curatorial',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'Curaduría ajustó el campo '.$this->campo.' de tu solicitud '.($this->numero ?? '').'. No se modificó la identificación de las especies.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            'icono' => 'pencil-square',
        ];
    }
}
