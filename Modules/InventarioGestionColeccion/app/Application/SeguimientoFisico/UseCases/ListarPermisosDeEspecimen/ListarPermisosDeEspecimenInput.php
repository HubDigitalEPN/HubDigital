<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarPermisosDeEspecimen;

final readonly class ListarPermisosDeEspecimenInput
{
    public function __construct(
        public string $especimenId,
    ) {}
}
