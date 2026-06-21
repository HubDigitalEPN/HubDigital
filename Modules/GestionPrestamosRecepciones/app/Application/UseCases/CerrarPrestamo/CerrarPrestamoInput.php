<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo;

/**
 * Datos de entrada para registrar el resultado de la verificación de devolución y cerrar el préstamo.
 */
final readonly class CerrarPrestamoInput
{
    /**
     * @param  array<array{itemPrestamoId: string, descripcion: string}>  $observaciones  Novedades por espécimen.
     */
    public function __construct(
        public string $prestamoId,
        public string $curadorId,
        public string $resultado,
        public array $observaciones = [],
        public ?string $observacionGeneral = null,
    ) {}
}
