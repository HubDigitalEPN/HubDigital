<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarPermisosDeEspecimen;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenPermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\PermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;

final class ListarPermisosDeEspecimenHandler
{
    public function __construct(
        private readonly EspecimenPermisoRepositoryInterface $pivote,
        private readonly PermisoRepositoryInterface $permisos,
    ) {}

    public function handle(ListarPermisosDeEspecimenInput $input): ListarPermisosDeEspecimenOutput
    {
        $permisoIds = $this->pivote->listarPermisosDe(EspecimenId::desde($input->especimenId));

        $items = [];
        foreach ($permisoIds as $pid) {
            $permiso = $this->permisos->buscarPorId($pid);
            if ($permiso === null) {
                continue;
            }
            $items[] = [
                'id' => (string) $permiso->id(),
                'tipo' => $permiso->tipo()->value,
                'numero' => $permiso->numero(),
                'responsable' => $permiso->responsable(),
                'detalles' => $permiso->detalles(),
            ];
        }

        return new ListarPermisosDeEspecimenOutput(
            especimenId: $input->especimenId,
            items: $items,
        );
    }
}
