<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class SolicitudEnviadaNotification extends Notification implements ShouldQueue
{
    use \App\Notifications\CanalesCampana;

    public function __construct(public readonly string $solicitudId, public readonly ?string $numero) {}

    public function via(object $notifiable): array { return $this->canalesCampana($notifiable); }

    public function toArray(object $notifiable): array
    {
        return ['tipo' => 'solicitud_enviada', 'solicitudId' => $this->solicitudId, 'numero' => $this->numero,
            'mensaje' => 'Tu solicitud '.($this->numero ?? '').' fue enviada y está pendiente de revisión por el Técnico.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId), 'icono' => 'paper-airplane'];
    }
}
