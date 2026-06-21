<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoGabinete;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\GabineteEloquentModel;

/**
 * Implementación Eloquent del repositorio de gabinetes: traduce entre la entidad Gabinete y
 * su modelo persistido y resuelve las consultas del componente (por id y solo los activos).
 */
class EloquentGabineteRepository implements GabineteRepository
{
    /** Genera un nuevo identificador de gabinete antes de persistirlo. */
    public function nextIdentity(): GabineteId
    {
        return GabineteId::generar();
    }

    /** Inserta o actualiza el gabinete según su id. */
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

    /** Recupera un gabinete por su identificador. */
    public function buscarPorId(GabineteId $id): ?Gabinete
    {
        $model = GabineteEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Lista los gabinetes activos, que son los que el barrido del ESP32 debe vigilar.
     *
     * @return Gabinete[]
     */
    public function buscarActivos(): array
    {
        return GabineteEloquentModel::where('activo', true)
            ->get()
            ->map(fn (GabineteEloquentModel $m) => $this->toDomain($m))
            ->all();
    }

    /** Reconstituye la entidad Gabinete a partir de la fila persistida. */
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
