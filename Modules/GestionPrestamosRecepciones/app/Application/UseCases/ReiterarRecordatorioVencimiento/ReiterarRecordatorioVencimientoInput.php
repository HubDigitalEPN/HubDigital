<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ReiterarRecordatorioVencimiento;

/**
 * Input DTO para reenviar manualmente el recordatorio de vencimiento de un préstamo.
 */
final readonly class ReiterarRecordatorioVencimientoInput
{
    /**
     * @param string $prestamoId ID del préstamo vencido a notificar.
     * @param string $curadorId ID del curador que ejecuta la acción.
     */
    public function __construct(
        public string $prestamoId,
        public string $curadorId,
    ) {}
}
