<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AsociarPermisoAEspecimen;

final readonly class AsociarPermisoAEspecimenOutput
{
    public function __construct(
        public string $especimenId,
        public string $permisoId,
        public bool $yaExistia,
    ) {}
}
