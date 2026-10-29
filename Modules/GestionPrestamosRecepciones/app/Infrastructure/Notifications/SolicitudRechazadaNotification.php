<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notifica al depositante que su solicitud fue rechazada por curaduría (requiere
 * corrección o rechazo permanente), incluyendo el comentario del curador. Se envía
 * por correo y por el portal (campana).
 */
final class SolicitudRechazadaNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \App\Notifications\CanalesCampana;
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly string $comentario,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->canalesCampana($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu solicitud requiere tu atención')
            ->view('gestionprestamosrecepciones::mails.deposito-rechazado', [
                'numero' => $this->numero,
                'comentario' => $this->comentario,
                'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'deposito_rechazado',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'Tu solicitud '.($this->numero ?? '').' fue devuelta por curaduría. Revisa el comentario.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            'icono' => 'exclamation-triangle',
        ];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return $this->mensajeWebPushCampana($notifiable);
    }

    private function eventoId(): string
    {
        return 'deposito-rechazo-'.$this->solicitudId;
    }
}
