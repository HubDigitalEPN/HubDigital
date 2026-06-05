<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EnlazarEspecimenALocalidad;

final readonly class EnlazarEspecimenALocalidadInput
{
    public function __construct(
        public string $especimenId,
        public string $localidadId,
    ) {}
}
