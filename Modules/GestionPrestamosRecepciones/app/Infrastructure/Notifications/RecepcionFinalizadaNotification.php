<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al depositante la finalización exitosa de la entrega física de su lote,
 * cuyos especímenes ingresaron a la colección. Se envía por correo y por el portal
 * (campana).
 */
final class RecepcionFinalizadaNotification extends Notification
{
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly string $estadoColeccion,
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
            ->subject('La recepción de tu lote finalizó con éxito')
            ->view('gestionprestamosrecepciones::mails.recepcion-finalizada', [
                'numero' => $this->numero,
                'estadoColeccion' => $this->estadoColeccion,
                'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'recepcion_finalizada',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'La entrega de tu lote '.($this->numero ?? '').' finalizó. Ingreso a colección: '.$this->estadoColeccion.'.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            'icono' => 'check-badge',
        ];
    }
}
