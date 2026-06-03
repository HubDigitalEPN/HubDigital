<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarPermisos;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\PermisoRepositoryInterface;

final class ListarPermisosHandler
{
    public function __construct(
        private readonly PermisoRepositoryInterface $permisoRepo,
    ) {}

    public function handle(): ListarPermisosOutput
    {
        $permisos = $this->permisoRepo->buscarTodos();

        $items = array_map(
            fn (Permiso $p) => new ListarPermisosItemOutput(
                id: (string) $p->id(),
                tipo: $p->tipo()->value,
                numero: $p->numero(),
                responsable: $p->responsable(),
                detalles: $p->detalles(),
            ),
            $permisos,
        );

        return new ListarPermisosOutput(items: $items, total: count($items));
    }
}
