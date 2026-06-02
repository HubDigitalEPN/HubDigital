<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\UnitTrayEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\UnitTrayId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\UnitTrayEspecimenEloquentModel;

class EloquentUnitTrayEspecimenRepository implements UnitTrayEspecimenRepository
{
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

    /** @return string[] */
    public function especimenIdsPorUnitTray(UnitTrayId $unitTrayId): array
    {
        return UnitTrayEspecimenEloquentModel::where('unit_tray_id', (string) $unitTrayId)
            ->pluck('especimen_id')
            ->all();
    }

    public function unitTrayDeEspecimen(string $especimenId): ?UnitTrayId
    {
        $model = UnitTrayEspecimenEloquentModel::where('especimen_id', $especimenId)->first();

        return $model ? UnitTrayId::desde($model->unit_tray_id) : null;
    }

    public function eliminarPorUnitTray(UnitTrayId $unitTrayId): void
    {
        UnitTrayEspecimenEloquentModel::where('unit_tray_id', (string) $unitTrayId)->delete();
    }
}
