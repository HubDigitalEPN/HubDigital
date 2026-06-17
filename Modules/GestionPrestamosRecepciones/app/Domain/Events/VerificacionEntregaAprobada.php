<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento de dominio emitido cuando el curador aprueba la verificación de entrega de un préstamo.
 */
final readonly class VerificacionEntregaAprobada
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $curadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
