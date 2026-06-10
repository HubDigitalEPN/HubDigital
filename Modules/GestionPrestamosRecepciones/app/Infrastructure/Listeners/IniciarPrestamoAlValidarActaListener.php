<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo\IniciarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo\IniciarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaValidada;

/**
 * Suscriptor de eventos que inicia un préstamo automáticamente tras la validación de su acta.
 *
 * Escucha el evento {@see ActaValidada} y ejecuta el caso de uso `IniciarPrestamoHandler`.
 */
final class IniciarPrestamoAlValidarActaListener
{
    /**
     * Constructor del listener que inicia el préstamo.
     *
     * @param IniciarPrestamoHandler $handler Manejador del caso de uso IniciarPrestamo.
     */
    public function __construct(private readonly IniciarPrestamoHandler $handler) {}

    /**
     * Maneja el evento de dominio delegando al caso de uso.
     */
    public function handle(ActaValidada $event): void
    {
        $this->handler->handle(new IniciarPrestamoInput(
            actaPrestamoId: (string) $event->actaId,
            solicitudId: (string) $event->solicitudId,
        ));
    }
}
