<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarSolicitudPrestamo;

final readonly class AprobarSolicitudPrestamoInput
{
    /**
     * @param  array<string, string>  $condicionesPorItem  itemId => condicion
     */
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public string $tipoPrestamo,
        public int $duracionMeses,
        public array $condicionesPorItem = [],
    ) {}
}
