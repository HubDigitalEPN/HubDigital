<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SepararEspecimenesEnNuevaMuestra;

final readonly class SepararEspecimenesEnNuevaMuestraOutput
{
    public function __construct(
        public string $nuevaMuestraId,
        public ?string $codigoMuestra,
        public int $especimenesSeparados,
    ) {}
}
