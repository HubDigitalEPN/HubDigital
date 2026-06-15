<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo;

/**
 * Datos de entrada para registrar el resultado de la verificación de devolución y cerrar el préstamo.
 */
final readonly class CerrarPrestamoInput
{
    public function __construct(
        public string $prestamoId,
        public string $curadorId,
        public string $resultado,
        public ?string $observacion = null,
    ) {}
}
