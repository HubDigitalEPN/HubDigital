<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarLocalidadCanonicaParaVerbatim;

final readonly class ConfirmarLocalidadCanonicaParaVerbatimOutput
{
    public function __construct(
        public string $verbatim,
        public string $localidadId,
        public int $especimenesEnlazados,
    ) {}
}
