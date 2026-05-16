<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\IdentificadorEspecimen;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EspecimenEloquentModel;

class EloquentEspecimenRepository implements EspecimenRepositoryInterface
{
    public function nextIdentity(): EspecimenId
    {
        return EspecimenId::generar();
    }

    public function guardar(Especimen $especimen): void
    {
        $model = EspecimenEloquentModel::updateOrCreate(
            ['id' => (string) $especimen->id()],
            [
                'codigo_catalogo' => $especimen->codigoCatalogo(),
                'occurrence_id' => $especimen->occurrenceId(),
                'catalog_number' => $especimen->catalogNumber(),
                'old_code' => $especimen->oldCode(),
                'cardex_liquid_collection_code' => $especimen->cardexLiquidCollectionCode(),
                'taxon_id' => $especimen->taxonId(),
                'localidad' => $especimen->localidad(),
                'fecha_colecta' => $especimen->fechaColecta(),
                'colector' => $especimen->colector(),
                'entidad_depositante_id' => $especimen->entidadDepositanteId(),
                'estado' => $especimen->estado()->value,
                'individual_count' => $especimen->individualCount(),
                'preparations' => $especimen->preparations(),
                'disposition' => $especimen->disposition(),
                'occurrence_status' => $especimen->occurrenceStatus(),
                'specimen_notes' => $especimen->specimenNotes(),
                'country' => $especimen->country(),
                'state_province' => $especimen->stateProvince(),
                'municipality' => $especimen->municipality(),
                'locality_name' => $especimen->localityName(),
                'decimal_latitude' => $especimen->decimalLatitude(),
                'decimal_longitude' => $especimen->decimalLongitude(),
                'geodetic_datum' => $especimen->geodeticDatum(),
                'elevation_in_meters' => $especimen->elevationInMeters(),
                'biome' => $especimen->biome(),
                'habitat' => $especimen->habitat(),
            ]
        );

        $model->identificadores()->delete();

        foreach ($especimen->identificadores() as $identificador) {
            $model->identificadores()->create([
                'id' => (string) Str::uuid(),
                'tipo' => $identificador->tipo()->value,
                'valor' => $identificador->valor(),
            ]);
        }
    }

    public function buscarPorId(EspecimenId $id): ?Especimen
    {
        $model = EspecimenEloquentModel::with('identificadores')->find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    /** @return Especimen[] */
    public function buscarPorEntidadDepositante(string $entidadDepositanteId): array
    {
        return EspecimenEloquentModel::with('identificadores')
            ->where('entidad_depositante_id', $entidadDepositanteId)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return Especimen[] */
    public function buscarPorLocalidad(string $localidad): array
    {
        return EspecimenEloquentModel::with('identificadores')
            ->where(function ($query) use ($localidad): void {
                $query->where('localidad', 'ilike', "%{$localidad}%")
                    ->orWhere('locality_name', 'ilike', "%{$localidad}%")
                    ->orWhere('state_province', 'ilike', "%{$localidad}%")
                    ->orWhere('municipality', 'ilike', "%{$localidad}%");
            })
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return Especimen[] */
    public function buscarPorEstado(string $estado): array
    {
        return EspecimenEloquentModel::with('identificadores')
            ->where('estado', $estado)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @param string[] $taxonIds
     *  @return Especimen[] */
    public function buscarPorTaxonIds(array $taxonIds): array
    {
        return EspecimenEloquentModel::with('identificadores')
            ->whereIn('taxon_id', $taxonIds)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function buscarPorCodigoCatalogo(string $codigo): ?Especimen
    {
        $model = EspecimenEloquentModel::with('identificadores')
            ->where('codigo_catalogo', $codigo)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /** @return Especimen[] */
    public function buscarPorIdentificador(string $tipo, string $valor): array
    {
        return EspecimenEloquentModel::with('identificadores')
            ->whereHas('identificadores', function ($query) use ($tipo, $valor): void {
                $query->where('tipo', $tipo)
                    ->where('valor', 'ilike', "%{$valor}%");
            })
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @return Especimen[] */
    public function buscarTodos(): array
    {
        return EspecimenEloquentModel::with('identificadores')
            ->get()
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
            occurrenceId: $model->occurrence_id,
            catalogNumber: $model->catalog_number,
            oldCode: $model->old_code,
            cardexLiquidCollectionCode: $model->cardex_liquid_collection_code,
            individualCount: $model->individual_count !== null ? (int) $model->individual_count : null,
            preparations: $model->preparations,
            disposition: $model->disposition,
            occurrenceStatus: $model->occurrence_status,
            specimenNotes: $model->specimen_notes,
            country: $model->country,
            stateProvince: $model->state_province,
            municipality: $model->municipality,
            localityName: $model->locality_name,
            decimalLatitude: $model->decimal_latitude !== null ? (float) $model->decimal_latitude : null,
            decimalLongitude: $model->decimal_longitude !== null ? (float) $model->decimal_longitude : null,
            geodeticDatum: $model->geodetic_datum,
            elevationInMeters: $model->elevation_in_meters !== null ? (float) $model->elevation_in_meters : null,
            biome: $model->biome,
            habitat: $model->habitat,
            identificadores: $model->identificadores
                ->map(fn ($identificador) => IdentificadorEspecimen::crear($identificador->tipo, $identificador->valor))
                ->all(),
        );
    }
}
