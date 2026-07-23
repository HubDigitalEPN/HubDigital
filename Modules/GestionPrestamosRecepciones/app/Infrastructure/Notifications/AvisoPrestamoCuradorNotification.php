<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso genérico al curador sobre un hito del proceso de préstamo. Se entrega solo
 * por el portal (campana); el correo al investigador lo cubren los listeners de correo.
 *
 * El mensaje ya viene armado por NotificarCuradorEventoPrestamoListener, que es quien
 * conoce cada evento.
 */
final class AvisoPrestamoCuradorNotification extends Notification
{
    public function __construct(
        public readonly string $tipo,
        public readonly string $mensaje,
        public readonly string $url,
        public readonly string $icono,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => $this->tipo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
            'icono' => $this->icono,
        ];
    }
}
