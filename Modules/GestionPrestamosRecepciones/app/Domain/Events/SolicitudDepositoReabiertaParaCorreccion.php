<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando el depositante reabre para corrección una
 * solicitud que estaba en "Requiere Corrección", volviéndola a "En Borrador".
 */
final class SolicitudDepositoReabiertaParaCorreccion extends DomainEvent
{
    public function __construct(
        public readonly SolicitudDepositoId $solicitudId,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'solicitud_deposito.reabierta_para_correccion';
    }
}
