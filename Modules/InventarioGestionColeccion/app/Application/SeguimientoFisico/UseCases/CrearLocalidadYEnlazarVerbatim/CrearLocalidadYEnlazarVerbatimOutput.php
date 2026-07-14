<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearLocalidadYEnlazarVerbatim;

final readonly class CrearLocalidadYEnlazarVerbatimOutput
{
    public function __construct(
        public string $localidadId,
        public string $nombreCanonico,
        public int $especimenesEnlazados,
    ) {}
}
