<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando un investigador crea una solicitud de depósito o donación.
 */
final class SolicitudDepositoCreada extends DomainEvent
{
    public function __construct(
        public readonly SolicitudDepositoId $solicitudId,
        public readonly string $investigadorId,
        public readonly string $tipoTramite,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'solicitud_deposito.creada';
    }
}
