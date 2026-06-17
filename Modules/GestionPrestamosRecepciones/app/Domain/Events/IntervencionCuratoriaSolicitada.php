<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando una solicitud sin documentación se escala a intervención curatorial.
 */
final class IntervencionCuratoriaSolicitada extends DomainEvent
{
    public function __construct(
        public readonly SolicitudDepositoId $solicitudId,
        public readonly string $investigadorId,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'solicitud_deposito.intervencion_curatoria_solicitada';
    }
}
