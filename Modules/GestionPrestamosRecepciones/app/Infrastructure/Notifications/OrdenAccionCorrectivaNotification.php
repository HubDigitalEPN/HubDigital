<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al depositante la orden de acción correctiva emitida al suspenderse la
 * recepción de su lote por una anomalía subsanable. El Código QR sigue vigente para
 * reintentar la recepción. Se envía por correo y por el portal (campana).
 */
final class OrdenAccionCorrectivaNotification extends Notification
{
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly string $motivoFallo,
        public readonly string $accionCorrectiva,
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
            ->subject('Acción correctiva requerida para la recepción de tu lote')
            ->view('gestionprestamosrecepciones::mails.orden-accion-correctiva', [
                'numero' => $this->numero,
                'motivoFallo' => $this->motivoFallo,
                'accionCorrectiva' => $this->accionCorrectiva,
                'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'orden_accion_correctiva',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'Se suspendió la recepción de tu lote '.($this->numero ?? '').' por: '.$this->motivoFallo.'. Acción requerida: '.$this->accionCorrectiva.'.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            'icono' => 'arrow-path',
        ];
    }
}
