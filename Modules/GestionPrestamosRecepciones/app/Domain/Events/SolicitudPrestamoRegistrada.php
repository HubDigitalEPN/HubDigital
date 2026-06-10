<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Evento de dominio emitido cuando se registra (crea) una solicitud de préstamo.
 */
final readonly class SolicitudPrestamoRegistrada
{
    public function __construct(
        public SolicitudPrestamoId $solicitudId,
        public string $investigadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
