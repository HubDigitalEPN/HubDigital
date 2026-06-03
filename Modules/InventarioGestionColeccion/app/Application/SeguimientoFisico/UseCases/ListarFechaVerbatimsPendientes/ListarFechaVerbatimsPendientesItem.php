<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarFechaVerbatimsPendientes;

final readonly class ListarFechaVerbatimsPendientesItem
{
    public function __construct(
        public string $verbatim,
        public int $totalEspecimenes,
        public ?string $sugerenciaInicio,
        public ?string $sugerenciaFin,
    ) {}

    public function tieneSugerencia(): bool
    {
        return $this->sugerenciaInicio !== null;
    }
}
