<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\Coordenada;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\LocalidadEloquentModel;

class EloquentLocalidadRepository implements LocalidadRepositoryInterface
{
    public function nextIdentity(): LocalidadId
    {
        return LocalidadId::generar();
    }

    public function guardar(Localidad $localidad): void
    {
        LocalidadEloquentModel::updateOrCreate(
            ['id' => (string) $localidad->id()],
            [
                'nombre_canonico' => $localidad->nombreCanonico(),
                'rango' => $localidad->rango()->value,
                'padre_id' => $localidad->padreId() ? (string) $localidad->padreId() : null,
                'decimal_latitude' => $localidad->coordenada()?->latitud,
                'decimal_longitude' => $localidad->coordenada()?->longitud,
                'geodetic_datum' => $localidad->geodeticDatum(),
                'coordinate_uncertainty_m' => $localidad->coordinateUncertaintyM(),
                'lat_lon_verbatim' => $localidad->latLonVerbatim(),
                'country' => $localidad->country(),
                'state_province' => $localidad->stateProvince(),
                'municipality' => $localidad->municipality(),
            ]
        );
    }

    public function buscarPorId(LocalidadId $id): ?Localidad
    {
        $model = LocalidadEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorNombreYRango(string $nombreCanonico, RangoLocalidad $rango): ?Localidad
    {
        $model = LocalidadEloquentModel::where('nombre_canonico', $nombreCanonico)
            ->where('rango', $rango->value)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** @return Localidad[] */
    public function buscarPorNombreContiene(string $fragmento): array
    {
        return LocalidadEloquentModel::where('nombre_canonico', 'ilike', "%{$fragmento}%")
            ->orderBy('nombre_canonico')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return Localidad[] */
    public function buscarTodos(): array
    {
        return LocalidadEloquentModel::orderBy('nombre_canonico')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function contarTodos(): int
    {
        return LocalidadEloquentModel::count();
    }

    /** @return Localidad[] */
    public function listarPagina(int $offset, int $limit): array
    {
        return LocalidadEloquentModel::orderBy('nombre_canonico')
            ->skip(max(0, $offset))
            ->take(max(1, $limit))
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /**
     * @param  string[]  $ids
     * @return Localidad[]
     */
    public function buscarPorIds(array $ids): array
    {
        return LocalidadEloquentModel::whereIn('id', $ids)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(LocalidadEloquentModel $model): Localidad
    {
        $coordenada = null;
        if ($model->decimal_latitude !== null && $model->decimal_longitude !== null) {
            $coordenada = Coordenada::desde(
                (float) $model->decimal_latitude,
                (float) $model->decimal_longitude,
            );
        }

        return Localidad::reconstituir(
            id: LocalidadId::desde($model->id),
            nombreCanonico: $model->nombre_canonico,
            rango: RangoLocalidad::from($model->rango),
            padreId: $model->padre_id !== null ? LocalidadId::desde($model->padre_id) : null,
            coordenada: $coordenada,
            geodeticDatum: $model->geodetic_datum,
            coordinateUncertaintyM: $model->coordinate_uncertainty_m !== null ? (float) $model->coordinate_uncertainty_m : null,
            latLonVerbatim: $model->lat_lon_verbatim,
            country: $model->country,
            stateProvince: $model->state_province,
            municipality: $model->municipality,
        );
    }
}
