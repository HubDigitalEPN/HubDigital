<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo;

/**
 * Datos de entrada para registrar una solicitud de préstamo.
 */
final readonly class RegistrarSolicitudPrestamoInput
{
    /**
     * @param  list<array{especimen_id: string, cantidad_solicitada: int}>  $items  El código y el nombre científico se resuelven contra el catálogo, no se aceptan del cliente.
     */
    public function __construct(
        public string $investigadorId,
        public string $alcancePrestamo,
        public string $tituloEstudio,
        public string $institucionAdscripcion,
        public string $lineaInvestigacion,
        public string $propositoPrestamo,
        public int $duracionPropuestaMeses,
        public array $items,
        public ?string $justificacionExtendida = null,
    ) {}
}
