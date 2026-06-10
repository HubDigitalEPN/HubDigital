<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando se carga e integra la documentación oficial de una solicitud de depósito.
 */
final class DocumentacionOficialCargada extends DomainEvent
{
    /**
     * @param  string[]  $nombresDocumentos
     */
    public function __construct(
        public readonly SolicitudDepositoId $solicitudId,
        public readonly array $nombresDocumentos,
    ) {
        parent::__construct();
    }

    public function nombreEvento(): string
    {
        return 'solicitud_deposito.documentacion_oficial_cargada';
    }
}
