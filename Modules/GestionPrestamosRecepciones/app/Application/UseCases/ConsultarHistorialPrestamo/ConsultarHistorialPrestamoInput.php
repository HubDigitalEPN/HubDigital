<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo;

/**
 * Input DTO para el caso de uso de consultar el historial de un préstamo.
 */
final readonly class ConsultarHistorialPrestamoInput
{
    /**
     * @param string $prestamoId ID del préstamo.
     * @param string $usuarioId ID del usuario que consulta el historial.
     */
    public function __construct(
        public string $prestamoId,
        public string $usuarioId,
    ) {}
}
