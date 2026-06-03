<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AsociarPermisoAEspecimen;

final readonly class AsociarPermisoAEspecimenInput
{
    public function __construct(
        public string $especimenId,
        public string $permisoId,
    ) {}
}
