<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al depositante que EPN constató el lote con observaciones que curaduría
 * incorporará después al acta final.
 */
final class RecepcionConObservacionesNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \App\Notifications\CanalesCampana;
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
        return $this->canalesCampana($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('EPN constató tu lote con observaciones')
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
            'mensaje' => 'EPN constató tu lote '.($this->numero ?? '').' con observaciones. Curaduría las incorporará al acta final.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            'icono' => 'exclamation-triangle',
        ];
    }
}
