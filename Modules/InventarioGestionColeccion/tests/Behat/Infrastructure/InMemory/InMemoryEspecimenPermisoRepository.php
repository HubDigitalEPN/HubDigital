<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenPermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;

final class InMemoryEspecimenPermisoRepository implements EspecimenPermisoRepositoryInterface
{
    /** @var array<int, array{especimen_id: string, permiso_id: string}> */
    private array $asociaciones = [];

    public function asociar(EspecimenId $especimenId, PermisoId $permisoId): void
    {
        if ($this->existeAsociacion($especimenId, $permisoId)) {
            return;
        }

        $this->asociaciones[] = [
            'especimen_id' => (string) $especimenId,
            'permiso_id' => (string) $permisoId,
        ];
    }

    public function desasociar(EspecimenId $especimenId, PermisoId $permisoId): void
    {
        $this->asociaciones = array_values(array_filter(
            $this->asociaciones,
            fn (array $a) => ! ($a['especimen_id'] === (string) $especimenId
                && $a['permiso_id'] === (string) $permisoId)
        ));
    }

    public function existeAsociacion(EspecimenId $especimenId, PermisoId $permisoId): bool
    {
        foreach ($this->asociaciones as $a) {
            if ($a['especimen_id'] === (string) $especimenId && $a['permiso_id'] === (string) $permisoId) {
                return true;
            }
        }

        return false;
    }

    /** @return PermisoId[] */
    public function listarPermisosDe(EspecimenId $especimenId): array
    {
        $ids = [];
        foreach ($this->asociaciones as $a) {
            if ($a['especimen_id'] === (string) $especimenId) {
                $ids[] = PermisoId::desde($a['permiso_id']);
            }
        }

        return $ids;
    }

    /** @return EspecimenId[] */
    public function listarEspecimenesDe(PermisoId $permisoId): array
    {
        $ids = [];
        foreach ($this->asociaciones as $a) {
            if ($a['permiso_id'] === (string) $permisoId) {
                $ids[] = EspecimenId::desde($a['especimen_id']);
            }
        }

        return $ids;
    }
}
