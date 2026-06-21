<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarRecordatoriosPrestamo;

use DateTimeImmutable;

/**
 * Fila de lectura de un recordatorio de devolución programado.
 *
 * {@see ConsultarRecordatoriosPrestamoOutput}
 */
final readonly class RecordatorioVista
{
    public function __construct(
        public int $diasAntes,
        public DateTimeImmutable $fechaProgramada,
    ) {}
}
