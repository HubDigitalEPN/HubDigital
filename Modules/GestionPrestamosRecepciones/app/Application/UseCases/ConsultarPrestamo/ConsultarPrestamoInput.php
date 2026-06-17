<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo;

/**
 * Input DTO para el caso de uso de consultar un préstamo.
 */
final readonly class ConsultarPrestamoInput
{
    /**
     * @param string $prestamoId ID del préstamo.
     * @param string $usuarioId ID del usuario que realiza la consulta.
     */
    public function __construct(
        public string $prestamoId,
        public string $usuarioId,
    ) {}
}
