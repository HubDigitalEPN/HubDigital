<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarPrestamo;

/**
 * Datos de entrada para iniciar un préstamo.
 */
final readonly class IniciarPrestamoInput
{
    public function __construct(
        public string $actaPrestamoId,
        public string $solicitudId,
    ) {}
}
