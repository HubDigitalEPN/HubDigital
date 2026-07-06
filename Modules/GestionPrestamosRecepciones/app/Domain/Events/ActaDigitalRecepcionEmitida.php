<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;

/**
 * Evento de dominio emitido cuando se emite el Acta Digital de Recepción al cerrar la
 * recepción del lote (conforme o con observaciones). El PDF se materializa en
 * Infraestructura a partir de la ruta indicada.
 */
final readonly class ActaDigitalRecepcionEmitida
{
    public function __construct(
        public SolicitudDepositoId $solicitudId,
        public string $ruta,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
