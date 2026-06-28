<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al depositante que su solicitud fue aprobada documentalmente y que su
 * Código QR de lote está disponible para la entrega física. Se envía por correo y
 * por el portal (campana).
 */
final class CodigoQrDisponibleNotification extends Notification
{
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly string $codigoQR,
        public readonly string $tipoTramite,
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
            ->subject('Tu solicitud de '.mb_strtolower($this->tipoTramite).' fue aprobada')
            ->view('gestionprestamosrecepciones::mails.deposito-aprobado', [
                'numero' => $this->numero,
                'codigoQR' => $this->codigoQR,
                'tipoTramite' => $this->tipoTramite,
                'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'deposito_aprobado',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'Tu solicitud '.($this->numero ?? '').' fue aprobada. Lote '.$this->codigoQR.' listo.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            'icono' => 'check-badge',
        ];
    }
}
