<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EspecimenEloquentModel;

class EloquentEspecimenRepository implements EspecimenRepositoryInterface
{
    public function nextIdentity(): EspecimenId
    {
        return EspecimenId::generar();
    }

    public function guardar(Especimen $especimen): void
    {
        EspecimenEloquentModel::updateOrCreate(
            ['id' => (string) $especimen->id()],
            [
                'codigo_catalogo' => $especimen->codigoCatalogo(),
                'taxon_id' => $especimen->taxonId(),
                'localidad' => $especimen->localidad(),
                'fecha_colecta' => $especimen->fechaColecta(),
                'colector' => $especimen->colector(),
                'entidad_depositante_id' => $especimen->entidadDepositanteId(),
                'estado' => $especimen->estado()->value,
            ]
        );
    }

    public function buscarPorId(EspecimenId $id): ?Especimen
    {
        $model = EspecimenEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /** @return Especimen[] */
    public function buscarPorEntidadDepositante(string $entidadDepositanteId): array
    {
        return EspecimenEloquentModel::where('entidad_depositante_id', $entidadDepositanteId)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return Especimen[] */
    public function buscarPorLocalidad(string $localidad): array
    {
        return EspecimenEloquentModel::where('localidad', 'ilike', "%{$localidad}%")
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return Especimen[] */
    public function buscarPorEstado(string $estado): array
    {
        return EspecimenEloquentModel::where('estado', $estado)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @param string[] $taxonIds
     *  @return Especimen[] */
    public function buscarPorTaxonIds(array $taxonIds): array
    {
        return EspecimenEloquentModel::whereIn('taxon_id', $taxonIds)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return Especimen[] */
    public function buscarTodos(): array
    {
        return EspecimenEloquentModel::all()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(EspecimenEloquentModel $model): Especimen
    {
        return Especimen::reconstituir(
            id: EspecimenId::desde($model->id),
            codigoCatalogo: $model->codigo_catalogo,
            taxonId: $model->taxon_id,
            localidad: $model->localidad,
            fechaColecta: $model->fecha_colecta,
            colector: $model->colector,
            estado: EstadoEspecimen::from($model->estado),
            entidadDepositanteId: $model->entidad_depositante_id,
        );
    }
}
