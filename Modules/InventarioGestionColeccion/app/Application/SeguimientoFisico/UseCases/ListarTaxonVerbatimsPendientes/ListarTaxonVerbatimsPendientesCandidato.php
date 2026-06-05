<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxonVerbatimsPendientes;

final readonly class ListarTaxonVerbatimsPendientesCandidato
{
    public function __construct(
        public string $taxonId,
        public string $nombreCientifico,
        public string $rango,
        public float $puntajeSimilitud,
    ) {}
}
