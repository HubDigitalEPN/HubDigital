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
        public readonly ?string $nombreInvestigador = null,
        public readonly ?string $fechaEnvio = null,
        public readonly bool $esReenvio = false,
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
        $investigador = $this->nombreInvestigador ?: 'Un investigador';

        $asunto = $this->esReenvio
            ? sprintf('Solicitud corregida reenviada%s', $this->numero ? ' — '.$this->numero : '')
            : sprintf('Nueva solicitud de %s por revisar%s', $tramite, $this->numero ? ' — '.$this->numero : '');

        return (new MailMessage)
            ->subject($asunto)
            ->view('gestionprestamosrecepciones::mails.nueva-solicitud-revisar', [
                'numero' => $this->numero,
                'tipoTramite' => $this->tipoTramite,
                'nombreInvestigador' => $investigador,
                'fechaEnvio' => $this->fechaEnvio,
                'nombreCurador' => $notifiable->name ?? null,
                'esReenvio' => $this->esReenvio,
                'url' => route('prestamos.curador.deposito.revisar', $this->solicitudId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $tramite = $this->tipoTramite ? mb_strtolower($this->tipoTramite) : 'depósito';
        $quien = $this->nombreInvestigador ?: 'Un investigador';
        $sufijo = $this->numero ? ' ('.$this->numero.')' : '';

        $mensaje = $this->esReenvio
            ? sprintf('%s corrigió y reenvió su solicitud de %s%s.', $quien, $tramite, $sufijo)
            : sprintf('%s envió una solicitud de %s%s para revisión.', $quien, $tramite, $sufijo);

        return [
            'tipo' => $this->esReenvio ? 'solicitud_corregida_reenviada' : 'nueva_solicitud_revisar',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => $mensaje,
            'url' => route('prestamos.curador.deposito.revisar', $this->solicitudId),
            'icono' => $this->esReenvio ? 'arrow-path' : 'inbox-arrow-down',
        ];
    }
}
