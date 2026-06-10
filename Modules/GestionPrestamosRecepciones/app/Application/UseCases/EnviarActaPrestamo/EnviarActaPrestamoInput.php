<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo;

/**
 * Input DTO para el caso de uso de enviar un acta de préstamo al investigador.
 */
final readonly class EnviarActaPrestamoInput
{
    /**
     * @param string $actaId ID del acta de préstamo a enviar.
     * @param string $curadorId ID del curador que realiza el envío.
     */
    public function __construct(
        public string $actaId,
        public string $curadorId,
    ) {}
}
