<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesasociarPermisoDeEspecimen;

final readonly class DesasociarPermisoDeEspecimenOutput
{
    public function __construct(
        public string $especimenId,
        public string $permisoId,
    ) {}
}
