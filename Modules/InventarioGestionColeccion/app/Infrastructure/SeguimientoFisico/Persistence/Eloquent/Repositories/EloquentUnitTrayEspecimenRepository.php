<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\UnitTrayEspecimenEloquentModel;

/**
 * Implementación Eloquent del repositorio de asignación entre unit trays y especímenes:
 * gestiona la tabla pivote, garantizando que un espécimen quede en un solo unit tray a la vez
 * y resolviendo las consultas inversas (qué especímenes hay en un tray y en qué tray está cada uno).
 */
class EloquentUnitTrayEspecimenRepository implements UnitTrayEspecimenRepository
{
    /**
     * Reemplaza el conjunto de especímenes de un unit tray por el recibido: borra las
     * asignaciones actuales del tray y, para cada espécimen, lo reasigna si ya pertenecía a
     * otro tray o crea la asignación, evitando duplicar un espécimen en varias bandejas.
     *
     * @param  string[]  $especimenIds
     */
    public function sincronizar(UnitTrayId $unitTrayId, array $especimenIds): void
    {
        $tray = (string) $unitTrayId;

        // Limpia las asignaciones actuales del tray y reasigna el conjunto recibido.
        UnitTrayEspecimenEloquentModel::where('unit_tray_id', $tray)->delete();

        foreach (array_values(array_unique($especimenIds)) as $especimenId) {
            $existente = UnitTrayEspecimenEloquentModel::where('especimen_id', $especimenId)->first();

            if ($existente !== null) {
                $existente->unit_tray_id = $tray;
                $existente->save();

                continue;
            }

            UnitTrayEspecimenEloquentModel::create([
                'id' => (string) Str::uuid(),
                'unit_tray_id' => $tray,
                'especimen_id' => $especimenId,
            ]);
        }
    }

    /**
     * Devuelve los ids de los especímenes asignados a un unit tray.
     *
     * @return string[]
     */
    public function especimenIdsPorUnitTray(UnitTrayId $unitTrayId): array
    {
        return UnitTrayEspecimenEloquentModel::where('unit_tray_id', (string) $unitTrayId)
            ->pluck('especimen_id')
            ->all();
    }

    /** Devuelve el unit tray al que pertenece un espécimen, o null si no está asignado a ninguno. */
    public function unitTrayDeEspecimen(string $especimenId): ?UnitTrayId
    {
        $model = UnitTrayEspecimenEloquentModel::where('especimen_id', $especimenId)->first();

        return $model ? UnitTrayId::desde($model->unit_tray_id) : null;
    }

    /**
     * Resuelve en bloque a qué unit tray pertenece cada espécimen de la lista, devolviendo un
     * mapa espécimen → unit tray para evitar consultas repetidas.
     *
     * @param  string[]  $especimenIds
     * @return array<string, string>
     */
    public function unitTraysDeEspecimenes(array $especimenIds): array
    {
        if ($especimenIds === []) {
            return [];
        }

        return UnitTrayEspecimenEloquentModel::whereIn('especimen_id', $especimenIds)
            ->pluck('unit_tray_id', 'especimen_id')
            ->all();
    }

    /** Elimina todas las asignaciones de especímenes de un unit tray (al borrar la bandeja). */
    public function eliminarPorUnitTray(UnitTrayId $unitTrayId): void
    {
        UnitTrayEspecimenEloquentModel::where('unit_tray_id', (string) $unitTrayId)->delete();
    }
}
