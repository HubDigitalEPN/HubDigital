<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes;

final readonly class ListarLocalidadVerbatimsPendientesItem
{
    /** @param ListarLocalidadVerbatimsPendientesCandidato[] $candidatos */
    public function __construct(
        public string $verbatim,
        public int $totalEspecimenes,
        public array $candidatos,
    ) {}
}
