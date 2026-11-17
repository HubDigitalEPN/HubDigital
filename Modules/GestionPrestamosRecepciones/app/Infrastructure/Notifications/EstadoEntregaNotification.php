<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use App\Notifications\CanalesCampana;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class EstadoEntregaNotification extends Notification implements ShouldQueue
{
    use CanalesCampana;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public string $solicitudId, public string $titulo, public string $motivo) {}

    public function via(object $notifiable): array
    {
        return $this->canalesCampana($notifiable);
    }

    public function toArray(object $notifiable): array
    {
        return ['tipo' => 'estado_entrega', 'solicitudId' => $this->solicitudId,
            'mensaje' => $this->titulo.'. '.$this->motivo,
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId)];
    }
}
