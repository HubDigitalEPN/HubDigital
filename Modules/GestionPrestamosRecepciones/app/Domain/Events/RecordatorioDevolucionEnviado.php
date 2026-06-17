<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Events;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecordatorio;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Evento de dominio emitido cuando se envía un recordatorio de devolución (próximo a vencer o vencido) al investigador.
 */
final readonly class RecordatorioDevolucionEnviado
{
    public function __construct(
        public PrestamoId $prestamoId,
        public string $investigadorId,
        public EstadoRecordatorio $estadoRecordatorio,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
