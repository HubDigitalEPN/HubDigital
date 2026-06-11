<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Evento de dominio emitido cuando el curador devuelve la solicitud con una observación.
 */
final readonly class SolicitudPrestamoObservada
{
    public function __construct(
        public SolicitudPrestamoId $solicitudId,
        public string $curadorId,
        public string $observacion,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
