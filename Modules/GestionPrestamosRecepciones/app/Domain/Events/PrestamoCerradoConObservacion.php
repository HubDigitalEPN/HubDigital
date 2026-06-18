<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento emitido cuando el curador cierra el préstamo con observaciones sobre la devolución.
 */
final readonly class PrestamoCerradoConObservacion
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $curadorId,
        public string $observacion,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
