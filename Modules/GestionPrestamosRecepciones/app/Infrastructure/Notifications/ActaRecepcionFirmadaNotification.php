<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al depositante que el Acta de Recepción, firmada electrónicamente por el
 * curador, ya está disponible para descargar. Se envía por correo y por el portal
 * (campana).
 */
final class ActaRecepcionFirmadaNotification extends Notification
{
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
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
            ->subject('Tu acta de recepción firmada ya está disponible')
            ->view('gestionprestamosrecepciones::mails.acta-recepcion-firmada', [
                'numero' => $this->numero,
                'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'acta_recepcion_firmada',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'El acta de recepción de tu depósito '.($this->numero ?? '').' fue firmada por el curador y ya puedes descargarla.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            'icono' => 'shield-check',
        ];
    }
}
