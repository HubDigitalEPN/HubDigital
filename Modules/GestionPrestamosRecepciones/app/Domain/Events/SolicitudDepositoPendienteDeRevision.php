<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando una solicitud de depósito avanza a revisión por curaduría.
 */
final class SolicitudDepositoPendienteDeRevision extends DomainEvent
{
    public function __construct(
        public readonly SolicitudDepositoId $solicitudId,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'solicitud_deposito.pendiente_de_revision';
    }
}
