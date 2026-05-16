<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEntidadDepositante;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EntidadDepositanteEloquentModel;

class EloquentEntidadDepositanteRepository implements EntidadDepositanteRepositoryInterface
{
    public function nextIdentity(): EntidadDepositanteId
    {
        return EntidadDepositanteId::generar();
    }

    public function guardar(EntidadDepositante $entidad): void
    {
        EntidadDepositanteEloquentModel::updateOrCreate(
            ['id' => (string) $entidad->id()],
            [
                'nombre' => $entidad->nombre(),
                'tipo' => $entidad->tipo()->value,
                'contacto' => $entidad->contacto(),
            ]
        );
    }

    public function buscarPorId(EntidadDepositanteId $id): ?EntidadDepositante
    {
        $model = EntidadDepositanteEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorNombre(string $nombre): ?EntidadDepositante
    {
        $model = EntidadDepositanteEloquentModel::where('nombre', $nombre)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** @return EntidadDepositante[] */
    public function buscarTodas(): array
    {
        return EntidadDepositanteEloquentModel::all()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(EntidadDepositanteEloquentModel $model): EntidadDepositante
    {
        return EntidadDepositante::reconstituir(
            id: EntidadDepositanteId::desde($model->id),
            nombre: $model->nombre,
            tipo: TipoEntidadDepositante::from($model->tipo),
            contacto: $model->contacto,
        );
    }
}
