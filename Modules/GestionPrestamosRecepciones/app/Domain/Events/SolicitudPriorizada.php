<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrioridadSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando el curador clasifica la prioridad de una solicitud
 * en la cola de revisión curatorial.
 */
final readonly class SolicitudPriorizada
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public PrioridadSolicitud $prioridad,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
