<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo\IniciarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo\IniciarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaValidada;

final class IniciarPrestamoAlValidarActaListener
{
    public function __construct(private readonly IniciarPrestamoHandler $handler) {}

    public function handle(ActaValidada $event): void
    {
        $this->handler->handle(new IniciarPrestamoInput(
            actaPrestamoId: (string) $event->actaId,
            solicitudId: (string) $event->solicitudId,
        ));
    }
}
