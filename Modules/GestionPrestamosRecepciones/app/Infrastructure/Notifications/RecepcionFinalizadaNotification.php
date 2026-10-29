<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al depositante que EPN recibió y constató físicamente su lote. La
 * accesión todavía espera el acta final firmada por curaduría.
 */
final class RecepcionFinalizadaNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \App\Notifications\CanalesCampana;
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
        return $this->canalesCampana($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('EPN recibió y constató tu lote')
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
            'tipo' => 'recepcion_constatada',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'EPN recibió tu lote '.($this->numero ?? '').'. Curaduría preparará y firmará el acta antes de ingresarlo a la colección.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            'icono' => 'check-badge',
        ];
    }
}
