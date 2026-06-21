<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarRecordatoriosPrestamo;

/**
 * Datos de entrada para consultar los recordatorios de devolución de un préstamo.
 *
 * {@see ConsultarRecordatoriosPrestamoHandler}
 */
final readonly class ConsultarRecordatoriosPrestamoInput
{
    public function __construct(
        public string $prestamoId,
    ) {}
}
