<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando se genera el Acta de Transferencia de Dominio al
 * aprobar documentalmente una donación.
 */
final readonly class ActaTransferenciaDominioGenerada
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public string $ruta,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
