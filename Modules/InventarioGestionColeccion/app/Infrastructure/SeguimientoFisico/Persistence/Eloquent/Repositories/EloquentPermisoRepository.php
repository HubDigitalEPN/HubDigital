<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\PermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoPermiso;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\PermisoEloquentModel;

class EloquentPermisoRepository implements PermisoRepositoryInterface
{
    public function nextIdentity(): PermisoId
    {
        return PermisoId::generar();
    }

    public function guardar(Permiso $permiso): void
    {
        PermisoEloquentModel::updateOrCreate(
            ['id' => (string) $permiso->id()],
            [
                'tipo' => $permiso->tipo()->value,
                'numero' => $permiso->numero(),
                'responsable' => $permiso->responsable(),
                'detalles' => $permiso->detalles(),
            ]
        );
    }

    public function buscarPorId(PermisoId $id): ?Permiso
    {
        $model = PermisoEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /** @return Permiso[] */
    public function buscarPorTipo(TipoPermiso $tipo): array
    {
        return PermisoEloquentModel::where('tipo', $tipo->value)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return Permiso[] */
    public function buscarTodos(): array
    {
        return PermisoEloquentModel::orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function contarTodos(): int
    {
        return PermisoEloquentModel::count();
    }

    private function toDomain(PermisoEloquentModel $model): Permiso
    {
        return Permiso::reconstituir(
            id: PermisoId::desde($model->id),
            tipo: TipoPermiso::from($model->tipo),
            numero: $model->numero,
            responsable: $model->responsable,
            detalles: $model->detalles,
        );
    }
}
