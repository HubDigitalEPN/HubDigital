<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Emitido cuando el curador registra que devolvió a su depositante el material de un
 * depósito temporal. A partir de aquí ese material ya no está en la colección.
 */
final readonly class DepositoDevuelto
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public string $curadorId,
        public DateTimeImmutable $devueltoEn,
    ) {}
}
