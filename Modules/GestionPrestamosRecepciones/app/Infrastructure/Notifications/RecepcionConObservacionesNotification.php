<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al depositante la finalización de la entrega con observaciones registradas
 * en el Acta Digital de Recepción. Se envía por correo y por el portal (campana).
 */
final class RecepcionConObservacionesNotification extends Notification
{
    /**
     * @param  list<string>  $observaciones
     */
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly array $observaciones,
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
            ->subject('La recepción de tu lote finalizó con observaciones')
            ->view('gestionprestamosrecepciones::mails.recepcion-con-observaciones', [
                'numero' => $this->numero,
                'observaciones' => $this->observaciones,
                'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'recepcion_con_observaciones',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'La entrega de tu lote '.($this->numero ?? '').' finalizó con observaciones registradas en el acta.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            'icono' => 'exclamation-triangle',
        ];
    }
}
