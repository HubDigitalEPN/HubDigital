<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetallePrestamo;

/**
 * Input DTO para consultar el detalle completo de un préstamo (préstamo + acta + solicitud).
 */
final readonly class ConsultarDetallePrestamoInput
{
    /**
     * @param string $prestamoId Identificador del préstamo.
     */
    public function __construct(
        public string $prestamoId,
    ) {}
}
