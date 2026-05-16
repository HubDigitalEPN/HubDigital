<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

final class DatoFaltanteCompletado extends DomainEvent
{
    public function __construct(
        public readonly SolicitudDepositoId $solicitudId,
        public readonly string $campo,
        public readonly string $valor,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'solicitud_deposito.dato_faltante_completado';
    }
}
