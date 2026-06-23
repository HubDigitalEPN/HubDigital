<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al curador que hay una nueva solicitud de depósito/donación pendiente de
 * revisión documental (envío inicial o reenvío tras corrección). Se envía por correo
 * y por el portal (campana).
 */
final class NuevaSolicitudPorRevisarNotification extends Notification
{
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly ?string $tipoTramite,
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
            ->subject('Nueva solicitud por revisar')
            ->view('gestionprestamosrecepciones::mails.nueva-solicitud-revisar', [
                'numero' => $this->numero,
                'tipoTramite' => $this->tipoTramite,
                'url' => route('prestamos.curador.deposito.revisar', $this->solicitudId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'nueva_solicitud_revisar',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'Nueva solicitud '.($this->numero ?? '').' pendiente de revisión documental.',
            'url' => route('prestamos.curador.deposito.revisar', $this->solicitudId),
            'icono' => 'inbox-arrow-down',
        ];
    }
}
