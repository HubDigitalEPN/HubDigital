<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EnlazarEspecimenAMuestra;

final readonly class EnlazarEspecimenAMuestraOutput
{
    public function __construct(
        public string $especimenId,
        public string $muestraId,
    ) {}
}
