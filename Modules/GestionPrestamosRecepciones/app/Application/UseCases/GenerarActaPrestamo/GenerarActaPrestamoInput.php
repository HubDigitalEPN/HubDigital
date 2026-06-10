<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaPrestamo;

/**
 * Datos de entrada para generar un acta de préstamo.
 */
final readonly class GenerarActaPrestamoInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
    ) {}
}
