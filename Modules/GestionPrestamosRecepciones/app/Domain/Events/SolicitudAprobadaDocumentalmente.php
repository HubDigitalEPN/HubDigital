<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando el curador aprueba documentalmente una solicitud
 * de depósito o donación.
 */
final readonly class SolicitudAprobadaDocumentalmente
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public string $curadorId,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
