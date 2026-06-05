<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxonVerbatimsPendientes;

final readonly class ListarTaxonVerbatimsPendientesItem
{
    /** @param ListarTaxonVerbatimsPendientesCandidato[] $candidatos */
    public function __construct(
        public string $verbatim,
        public int $totalEspecimenes,
        public array $candidatos,
    ) {}
}
