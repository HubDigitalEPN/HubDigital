<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Identificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\IdentificacionRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\IdentificacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\IdentificacionEloquentModel;

class EloquentIdentificacionRepository implements IdentificacionRepositoryInterface
{
    public function nextIdentity(): IdentificacionId
    {
        return IdentificacionId::generar();
    }

    public function guardar(Identificacion $identificacion): void
    {
        IdentificacionEloquentModel::updateOrCreate(
            ['id' => (string) $identificacion->id()],
            [
                'especimen_id' => (string) $identificacion->especimenId(),
                'taxon_id' => $identificacion->taxonId() !== null ? (string) $identificacion->taxonId() : null,
                'identificado_por' => $identificacion->identificadoPor(),
                'fecha_determinacion' => $identificacion->fechaDeterminacion()?->format('Y-m-d'),
                'calificador' => $identificacion->calificador(),
                'observaciones' => $identificacion->observaciones(),
                'es_actual' => $identificacion->esActual(),
            ]
        );
    }

    public function buscarPorId(IdentificacionId $id): ?Identificacion
    {
        $model = IdentificacionEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /** @return Identificacion[] */
    public function listarPorEspecimen(EspecimenId $especimenId): array
    {
        return IdentificacionEloquentModel::where('especimen_id', (string) $especimenId)
            ->orderByDesc('es_actual')
            ->orderByDesc('fecha_determinacion')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function buscarActualDe(EspecimenId $especimenId): ?Identificacion
    {
        $model = IdentificacionEloquentModel::where('especimen_id', (string) $especimenId)
            ->where('es_actual', true)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function desactualizarTodasDe(EspecimenId $especimenId): void
    {
        IdentificacionEloquentModel::where('especimen_id', (string) $especimenId)
            ->where('es_actual', true)
            ->update(['es_actual' => false]);
    }

    private function toDomain(IdentificacionEloquentModel $model): Identificacion
    {
        $fecha = null;
        if ($model->fecha_determinacion !== null) {
            $fecha = $model->fecha_determinacion instanceof \DateTimeImmutable
                ? $model->fecha_determinacion
                : new \DateTimeImmutable((string) $model->fecha_determinacion);
        }

        return Identificacion::reconstituir(
            id: IdentificacionId::desde($model->id),
            especimenId: EspecimenId::desde($model->especimen_id),
            taxonId: $model->taxon_id !== null ? TaxonId::desde($model->taxon_id) : null,
            identificadoPor: $model->identificado_por,
            fechaDeterminacion: $fecha,
            calificador: $model->calificador,
            observaciones: $model->observaciones,
            esActual: (bool) $model->es_actual,
        );
    }
}
