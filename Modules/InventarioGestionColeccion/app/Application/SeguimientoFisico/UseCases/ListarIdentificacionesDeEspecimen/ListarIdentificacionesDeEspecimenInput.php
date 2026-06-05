<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarIdentificacionesDeEspecimen;

final readonly class ListarIdentificacionesDeEspecimenInput
{
    public function __construct(
        public string $especimenId,
    ) {}
}
