<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Visitante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\VisitanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\VisitanteId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\VisitanteEloquentModel;

class EloquentVisitanteRepository implements VisitanteRepositoryInterface
{
    public function nextIdentity(): VisitanteId
    {
        return VisitanteId::generar();
    }

    public function guardar(Visitante $visitante): void
    {
        VisitanteEloquentModel::updateOrCreate(
            ['id' => (string) $visitante->id()],
            [
                'nombre' => $visitante->nombre(),
                'contacto' => $visitante->contacto(),
                'version_acceso' => $visitante->versionAcceso(),
                'puede_reubicar' => $visitante->puedeReubicar(),
                'registrado_en' => $visitante->registradoEn()->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function buscarPorId(VisitanteId $id): ?Visitante
    {
        $model = VisitanteEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /** @return Visitante[] */
    public function buscarTodos(): array
    {
        return VisitanteEloquentModel::orderByDesc('registrado_en')
            ->get()
            ->map(fn (VisitanteEloquentModel $m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(VisitanteEloquentModel $model): Visitante
    {
        return Visitante::reconstituir(
            id: VisitanteId::desde($model->id),
            nombre: $model->nombre,
            contacto: $model->contacto,
            versionAcceso: (int) $model->version_acceso,
            puedeReubicar: (bool) $model->puede_reubicar,
            registradoEn: \DateTimeImmutable::createFromInterface($model->registrado_en),
        );
    }
}
