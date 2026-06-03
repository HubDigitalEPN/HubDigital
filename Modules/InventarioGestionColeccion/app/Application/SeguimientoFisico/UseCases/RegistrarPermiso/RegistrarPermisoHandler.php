<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarPermiso;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\PermisoRepositoryInterface;

final class RegistrarPermisoHandler
{
    public function __construct(
        private readonly PermisoRepositoryInterface $permisoRepo,
    ) {}

    public function handle(RegistrarPermisoInput $input): RegistrarPermisoOutput
    {
        $permiso = Permiso::crear(
            id: $this->permisoRepo->nextIdentity(),
            tipo: $input->tipo,
            numero: $input->numero,
            responsable: $input->responsable,
            detalles: $input->detalles,
        );

        $this->permisoRepo->guardar($permiso);

        return new RegistrarPermisoOutput(
            id: $permiso->id(),
            tipo: $permiso->tipo()->value,
            numero: $permiso->numero(),
            responsable: $permiso->responsable(),
            detalles: $permiso->detalles(),
        );
    }
}
