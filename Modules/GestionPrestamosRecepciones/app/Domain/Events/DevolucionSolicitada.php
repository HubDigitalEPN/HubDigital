<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento emitido cuando el investigador solicita el cierre del préstamo, indicando que ha devuelto los especímenes.
 */
final readonly class DevolucionSolicitada
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $investigadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
