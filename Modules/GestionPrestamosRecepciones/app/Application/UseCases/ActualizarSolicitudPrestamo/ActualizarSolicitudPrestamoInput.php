<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo;

/**
 * Input DTO para actualizar una solicitud de préstamo.
 */
final readonly class ActualizarSolicitudPrestamoInput
{
    /**
     * @param  list<array{id?: string, especimen_id: string, cantidad_solicitada: int}>  $items  El código y el nombre científico se resuelven contra el catálogo, no se aceptan del cliente.
     */
    public function __construct(
        public string $solicitudId,
        public string $investigadorId,
        public string $tituloEstudio,
        public string $institucionAdscripcion,
        public string $lineaInvestigacion,
        public string $propositoPrestamo,
        public int $duracionPropuestaMeses,
        public array $items,
        public ?string $justificacionExtendida = null,
    ) {}
}
