<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica a los demás curadores que un colega aprobó o rechazó documentalmente una
 * solicitud de depósito/donación. Incluye el motivo cuando fue un rechazo. Se envía
 * por correo y por el portal (campana).
 */
final class DecisionDocumentalCuradorNotification extends Notification
{
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly ?string $tipoTramite,
        public readonly string $decision,
        public readonly ?string $motivo = null,
        public readonly ?string $nombreCuradorDecide = null,
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
        $tramite = $this->tipoTramite ? mb_strtolower($this->tipoTramite) : 'depósito';
        $curador = $this->nombreCuradorDecide ?: 'Otro curador';
        $sufijo = $this->numero ? ' — '.$this->numero : '';
        $rechazada = $this->decision === 'rechazada';

        $asunto = $rechazada
            ? sprintf('Solicitud de %s rechazada por un curador%s', $tramite, $sufijo)
            : sprintf('Solicitud de %s aprobada por un curador%s', $tramite, $sufijo);

        $mail = (new MailMessage)
            ->subject($asunto)
            ->greeting('Hola'.($notifiable->name ? ', '.$notifiable->name : '').':')
            ->line(sprintf(
                '%s %s la solicitud de %s%s.',
                $curador,
                $rechazada ? 'rechazó' : 'aprobó',
                $tramite,
                $this->numero ? ' '.$this->numero : '',
            ));

        if ($rechazada && $this->motivo !== null && $this->motivo !== '') {
            $mail->line('Motivo del rechazo: '.$this->motivo);
        }

        return $mail
            ->action('Ver solicitud', route('prestamos.curador.deposito.revisar', $this->solicitudId))
            ->line('Este aviso es solo informativo; la solicitud ya fue resuelta.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $tramite = $this->tipoTramite ? mb_strtolower($this->tipoTramite) : 'depósito';
        $curador = $this->nombreCuradorDecide ?: 'Otro curador';
        $sufijo = $this->numero ? ' ('.$this->numero.')' : '';
        $rechazada = $this->decision === 'rechazada';

        $mensaje = $rechazada
            ? sprintf('%s rechazó la solicitud de %s%s.', $curador, $tramite, $sufijo)
            : sprintf('%s aprobó la solicitud de %s%s.', $curador, $tramite, $sufijo);

        if ($rechazada && $this->motivo !== null && $this->motivo !== '') {
            $mensaje .= ' Motivo: '.$this->motivo;
        }

        return [
            'tipo' => $rechazada ? 'solicitud_rechazada_por_curador' : 'solicitud_aprobada_por_curador',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => $mensaje,
            'url' => route('prestamos.curador.deposito.revisar', $this->solicitudId),
            'icono' => $rechazada ? 'x-circle' : 'check-circle',
        ];
    }
}
