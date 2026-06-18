<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoRechazoDocumental;

/**
 * Evento de dominio emitido cuando el curador rechaza documentalmente una solicitud,
 * indicando el tipo de rechazo (subsanable o definitivo) y el estado resultante.
 */
final readonly class SolicitudRechazadaDocumentalmente
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public string $curadorId,
        public TipoRechazoDocumental $tipoRechazo,
        public EstadoSolicitudDeposito $estadoResultante,
        public string $motivo,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
