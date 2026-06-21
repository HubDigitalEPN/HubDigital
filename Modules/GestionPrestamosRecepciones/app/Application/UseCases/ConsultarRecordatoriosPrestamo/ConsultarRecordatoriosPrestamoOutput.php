<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarRecordatoriosPrestamo;

/**
 * Resultado de la consulta de recordatorios de devolución de un préstamo.
 *
 * {@see ConsultarRecordatoriosPrestamoHandler}
 */
final readonly class ConsultarRecordatoriosPrestamoOutput
{
    /**
     * @param array<int, RecordatorioVista> $recordatorios
     */
    public function __construct(
        public array $recordatorios,
    ) {}
}
