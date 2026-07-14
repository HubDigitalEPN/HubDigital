<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando el curador inicia la recepción física del lote
 * entregado, tras acceder a la solicitud a partir de su Código QR.
 */
final readonly class RecepcionLoteIniciada
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public string $codigoQR,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
