<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaPrestamo;

/**
 * Input DTO para el caso de uso de consultar un acta de préstamo.
 */
final readonly class ConsultarActaPrestamoInput
{
    /**
     * @param string $actaId ID del acta de préstamo.
     * @param string $usuarioId ID del usuario que realiza la consulta.
     */
    public function __construct(
        public string $actaId,
        public string $usuarioId,
    ) {}
}
