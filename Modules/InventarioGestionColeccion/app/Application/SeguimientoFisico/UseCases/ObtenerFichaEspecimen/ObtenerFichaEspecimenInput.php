<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerFichaEspecimen;

final readonly class ObtenerFichaEspecimenInput
{
    public function __construct(
        public string $especimenId,
    ) {}
}
