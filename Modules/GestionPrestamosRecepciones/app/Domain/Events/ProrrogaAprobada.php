<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento de dominio emitido cuando el curador aprueba una prórroga y extiende la fecha de fin del préstamo.
 */
final readonly class ProrrogaAprobada
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $curadorId,
        public DateTimeImmutable $nuevaFechaFin,
        public DateTimeImmutable $fechaAnterior,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
