<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes;

final readonly class ListarLocalidadVerbatimsPendientesInput
{
    public function __construct(
        public int $limiteCandidatosPorVerbatim = 5,
    ) {}
}
