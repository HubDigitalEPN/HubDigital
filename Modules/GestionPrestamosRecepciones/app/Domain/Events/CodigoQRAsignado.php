<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando se asigna un Código QR único al lote de muestras
 * de una solicitud aprobada documentalmente.
 */
final readonly class CodigoQRAsignado
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public string $codigoQR,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
