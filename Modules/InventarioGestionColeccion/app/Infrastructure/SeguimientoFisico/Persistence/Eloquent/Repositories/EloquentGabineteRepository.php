<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\GabineteEloquentModel;

class EloquentGabineteRepository implements GabineteRepository
{
    public function nextIdentity(): GabineteId
    {
        return GabineteId::generar();
    }

    public function guardar(Gabinete $gabinete): void
    {
        GabineteEloquentModel::updateOrCreate(
            ['id' => (string) $gabinete->id()],
            [
                'codigo' => (string) $gabinete->codigo(),
                'nombre' => $gabinete->nombre(),
                'total_ranuras' => $gabinete->totalRanuras(),
                'activo' => $gabinete->activo(),
            ]
        );
    }

    public function buscarPorId(GabineteId $id): ?Gabinete
    {
        $model = GabineteEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    public function buscarActivos(): array
    {
        return GabineteEloquentModel::where('activo', true)
            ->get()
            ->map(fn (GabineteEloquentModel $m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(GabineteEloquentModel $model): Gabinete
    {
        return Gabinete::reconstituir(
            id: GabineteId::desde($model->id),
            codigo: CodigoGabinete::desde($model->codigo),
            nombre: $model->nombre,
            totalRanuras: $model->total_ranuras,
            activo: (bool) $model->activo,
        );
    }
}
