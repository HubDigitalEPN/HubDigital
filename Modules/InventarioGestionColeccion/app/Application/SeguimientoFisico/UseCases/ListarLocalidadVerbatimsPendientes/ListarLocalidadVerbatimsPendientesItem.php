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
        /**
         * Verbatim de un solo nombre, sin comas: el importador no puede
         * descomponerlo en jerarquía geográfica, así que necesita resolución
         * manual del curador. Se marca para que salte a la vista.
         */
        public bool $esNombreUnico = false,
    ) {}
}
