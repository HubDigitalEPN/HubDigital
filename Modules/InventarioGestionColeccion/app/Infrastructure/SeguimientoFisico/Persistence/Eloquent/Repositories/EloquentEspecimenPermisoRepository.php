<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenPermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;

class EloquentEspecimenPermisoRepository implements EspecimenPermisoRepositoryInterface
{
    private const TABLE = 'taxonomia.especimen_permiso';

    public function asociar(EspecimenId $especimenId, PermisoId $permisoId): void
    {
        if ($this->existeAsociacion($especimenId, $permisoId)) {
            return;
        }

        DB::table(self::TABLE)->insert([
            'especimen_id' => (string) $especimenId,
            'permiso_id' => (string) $permisoId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function desasociar(EspecimenId $especimenId, PermisoId $permisoId): void
    {
        DB::table(self::TABLE)
            ->where('especimen_id', (string) $especimenId)
            ->where('permiso_id', (string) $permisoId)
            ->delete();
    }

    public function existeAsociacion(EspecimenId $especimenId, PermisoId $permisoId): bool
    {
        return DB::table(self::TABLE)
            ->where('especimen_id', (string) $especimenId)
            ->where('permiso_id', (string) $permisoId)
            ->exists();
    }

    /** @return PermisoId[] */
    public function listarPermisosDe(EspecimenId $especimenId): array
    {
        return DB::table(self::TABLE)
            ->where('especimen_id', (string) $especimenId)
            ->pluck('permiso_id')
            ->map(fn ($id) => PermisoId::desde((string) $id))
            ->all();
    }

    /** @return EspecimenId[] */
    public function listarEspecimenesDe(PermisoId $permisoId): array
    {
        return DB::table(self::TABLE)
            ->where('permiso_id', (string) $permisoId)
            ->pluck('especimen_id')
            ->map(fn ($id) => EspecimenId::desde((string) $id))
            ->all();
    }
}
