<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;

/**
 * Evento de dominio emitido cuando el investigador envía la solicitud de préstamo para revisión.
 */
final readonly class SolicitudPrestamoEnviada
{
    public function __construct(
        public SolicitudPrestamoId $solicitudId,
        public DateTimeImmutable $enviadaEn,
    ) {}
}
