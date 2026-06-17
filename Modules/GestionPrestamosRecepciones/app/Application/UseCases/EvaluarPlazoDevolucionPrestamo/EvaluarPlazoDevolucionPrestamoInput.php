<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EvaluarPlazoDevolucionPrestamo;

/**
 * Input DTO para el caso de uso de evaluar el plazo de devolución de un préstamo.
 */
final readonly class EvaluarPlazoDevolucionPrestamoInput
{
    /**
     * @param string $prestamoId ID del préstamo a evaluar.
     */
    public function __construct(
        public string $prestamoId,
    ) {}
}
