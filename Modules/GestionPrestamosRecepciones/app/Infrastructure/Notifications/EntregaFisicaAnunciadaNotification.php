<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Notification;

/** Aviso interno de que un consultor anunció una entrega física aprobada. */
final class EntregaFisicaAnunciadaNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \App\Notifications\CanalesCampana;
    public function __construct(
        private readonly string $solicitudId,
        private readonly ?string $numero,
        private readonly mixed $programadaPara,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->canalesCampana($notifiable);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $fecha = $this->programadaPara?->format('d/m/Y H:i') ?? 'fecha por confirmar';

        return [
            'tipo' => 'entrega_fisica_anunciada',
            'solicitudId' => $this->solicitudId,
            'mensaje' => 'El consultor anunció la entrega del lote '.($this->numero ?? '').' para '.$fecha.'.',
            'url' => route('prestamos.receptor.deposito.recepcion', $this->solicitudId),
            'icono' => 'truck',
            'accion' => 'Abrir recepción',
            'prioridad' => 'media',
        ];
    }
}
