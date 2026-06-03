<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarPermiso;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\PermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;

final class ActualizarPermisoHandler
{
    public function __construct(
        private readonly PermisoRepositoryInterface $repo,
    ) {}

    public function handle(ActualizarPermisoInput $input): ActualizarPermisoOutput
    {
        $permiso = $this->repo->buscarPorId(PermisoId::desde($input->permisoId));
        if ($permiso === null) {
            throw new \DomainException("Permiso '{$input->permisoId}' no encontrado.");
        }

        $permiso->actualizar(
            tipo: $input->tipo,
            numero: $input->numero,
            responsable: $input->responsable,
            detalles: $input->detalles,
        );

        $this->repo->guardar($permiso);

        return new ActualizarPermisoOutput(
            id: (string) $permiso->id(),
            tipo: $permiso->tipo()->value,
            numero: $permiso->numero(),
            responsable: $permiso->responsable(),
            detalles: $permiso->detalles(),
        );
    }
}
