<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes;

final readonly class ListarLocalidadVerbatimsPendientesCandidato
{
    public function __construct(
        public string $localidadId,
        public string $nombreCanonico,
        public string $rango,
        public float $puntajeSimilitud,
    ) {}
}
