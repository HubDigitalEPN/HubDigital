<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo;

final readonly class RegistrarSolicitudPrestamoInput
{
    /**
     * @param  list<array{especimen_codigo_externo: string, cantidad_solicitada: int}>  $items
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
