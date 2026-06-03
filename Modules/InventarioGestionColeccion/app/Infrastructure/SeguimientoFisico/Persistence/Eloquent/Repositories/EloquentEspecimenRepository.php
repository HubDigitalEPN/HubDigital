<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;
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
                'taxon_verbatim' => $especimen->taxonVerbatim(),
                'muestra_id' => $especimen->muestraId(),
                'localidad_id' => $especimen->localidadId(),
                // BD acepta null (post-hardening). Bridge '' → null para que la entidad,
                // que sigue usando string vacío como "ausente", persista coherentemente.
                'localidad' => $this->stringNullable($especimen->localidad()),
                'localidad_verbatim' => $especimen->localidadVerbatim(),
                'fecha_colecta' => $this->stringNullable($especimen->fechaColecta()),
                'fecha_verbatim' => $especimen->fechaVerbatim(),
                'fecha_colecta_fin' => $especimen->fechaColectaFin(),
                'colector' => $this->stringNullable($especimen->colector()),
                'entidad_depositante_id' => $especimen->entidadDepositanteId(),
                'estado' => $especimen->estado()->value,
                'individual_count' => $especimen->individualCount(),
                'individual_count_verbatim' => $especimen->individualCountVerbatim(),
                'sex' => $especimen->sex(),
                'life_stage' => $especimen->lifeStage(),
                'caste' => $especimen->caste(),
                'type_status' => $especimen->typeStatus(),
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
                'coord_verbatim' => $especimen->coordVerbatim(),
                'geodetic_datum' => $especimen->geodeticDatum(),
                'elevation_min_m' => $especimen->elevationMinM(),
                'elevation_max_m' => $especimen->elevationMaxM(),
                'biome' => $especimen->biome(),
                'habitat' => $especimen->habitat(),
                'microhabitat' => $especimen->microhabitat(),
                'biogeographic_region' => $especimen->biogeographicRegion(),
                'endemic' => $especimen->endemic(),
                'dna_notes' => $especimen->dnaNotes(),
                'occurrence_remarks' => $especimen->occurrenceRemarks(),
                'taxonomic_notes' => $especimen->taxonomicNotes(),
                'acta_recepcion' => $especimen->actaRecepcion(),
                'estado_revision' => $especimen->estadoRevision()->value,
                'motivo_revision' => $especimen->motivoRevision(),
                'fila_origen_excel' => $especimen->filaOrigenExcel(),
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

    public function existePorFilaOrigen(int $filaOrigenExcel): bool
    {
        return EspecimenEloquentModel::where('fila_origen_excel', $filaOrigenExcel)->exists();
    }

    /** @return Especimen[] */
    public function buscarParaRevision(?string $contieneMotivo = null, int $limit = 200): array
    {
        $query = EspecimenEloquentModel::with('identificadores')
            ->where('estado_revision', 'pendiente')
            ->whereNotNull('motivo_revision');

        if ($contieneMotivo !== null && trim($contieneMotivo) !== '') {
            $query->where('motivo_revision', 'ilike', '%'.trim($contieneMotivo).'%');
        }

        return $query->limit($limit)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    /** @param int[] $filasOrigen
     *  @return int[] */
    public function filasOrigenExistentes(array $filasOrigen): array
    {
        if ($filasOrigen === []) {
            return [];
        }

        return EspecimenEloquentModel::whereIn('fila_origen_excel', $filasOrigen)
            ->pluck('fila_origen_excel')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function guardarBatch(array $especimenes): void
    {
        if ($especimenes === []) {
            return;
        }

        $ahora = now();
        $especimenRows = [];
        $identificadorRows = [];

        foreach ($especimenes as $especimen) {
            $especimenRows[] = [
                'id' => (string) $especimen->id(),
                'codigo_catalogo' => $especimen->codigoCatalogo(),
                'occurrence_id' => $especimen->occurrenceId(),
                'catalog_number' => $especimen->catalogNumber(),
                'old_code' => $especimen->oldCode(),
                'cardex_liquid_collection_code' => $especimen->cardexLiquidCollectionCode(),
                'taxon_id' => $especimen->taxonId(),
                'taxon_verbatim' => $especimen->taxonVerbatim(),
                'muestra_id' => $especimen->muestraId(),
                'localidad_id' => $especimen->localidadId(),
                'localidad' => $this->stringNullable($especimen->localidad()),
                'localidad_verbatim' => $especimen->localidadVerbatim(),
                'fecha_colecta' => $this->stringNullable($especimen->fechaColecta()),
                'fecha_verbatim' => $especimen->fechaVerbatim(),
                'fecha_colecta_fin' => $especimen->fechaColectaFin(),
                'colector' => $this->stringNullable($especimen->colector()),
                'entidad_depositante_id' => $especimen->entidadDepositanteId(),
                'estado' => $especimen->estado()->value,
                'individual_count' => $especimen->individualCount(),
                'individual_count_verbatim' => $especimen->individualCountVerbatim(),
                'sex' => $especimen->sex(),
                'life_stage' => $especimen->lifeStage(),
                'caste' => $especimen->caste(),
                'type_status' => $especimen->typeStatus(),
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
                'coord_verbatim' => $especimen->coordVerbatim(),
                'geodetic_datum' => $especimen->geodeticDatum(),
                'elevation_min_m' => $especimen->elevationMinM(),
                'elevation_max_m' => $especimen->elevationMaxM(),
                'biome' => $especimen->biome(),
                'habitat' => $especimen->habitat(),
                'microhabitat' => $especimen->microhabitat(),
                'biogeographic_region' => $especimen->biogeographicRegion(),
                'endemic' => $especimen->endemic(),
                'dna_notes' => $especimen->dnaNotes(),
                'occurrence_remarks' => $especimen->occurrenceRemarks(),
                'taxonomic_notes' => $especimen->taxonomicNotes(),
                'acta_recepcion' => $especimen->actaRecepcion(),
                'estado_revision' => $especimen->estadoRevision()->value,
                'motivo_revision' => $especimen->motivoRevision(),
                'fila_origen_excel' => $especimen->filaOrigenExcel(),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];

            foreach ($especimen->identificadores() as $identificador) {
                $identificadorRows[] = [
                    'id' => (string) Str::uuid(),
                    'especimen_id' => (string) $especimen->id(),
                    'tipo' => $identificador->tipo()->value,
                    'valor' => $identificador->valor(),
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        DB::transaction(function () use ($especimenRows, $identificadorRows): void {
            DB::table('taxonomia.especimenes')->insert($especimenRows);
            if ($identificadorRows !== []) {
                DB::table('taxonomia.especimen_identificadores')->insert($identificadorRows);
            }
        });
    }

    private function stringNullable(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $trim = trim($valor);

        return $trim === '' ? null : $trim;
    }

    private function toDomain(EspecimenEloquentModel $model): Especimen
    {
        $fechaColecta = $model->fecha_colecta !== null
            ? (is_string($model->fecha_colecta) ? $model->fecha_colecta : $model->fecha_colecta->format('Y-m-d'))
            : '';
        $fechaColectaFin = $model->fecha_colecta_fin !== null
            ? (is_string($model->fecha_colecta_fin) ? $model->fecha_colecta_fin : $model->fecha_colecta_fin->format('Y-m-d'))
            : null;

        return Especimen::reconstituir(
            id: EspecimenId::desde($model->id),
            codigoCatalogo: $model->codigo_catalogo,
            taxonId: $model->taxon_id,
            // BD puede tener null; la entidad usa '' para "ausente" en estos legacy fields.
            localidad: $model->localidad ?? '',
            fechaColecta: $fechaColecta,
            colector: $model->colector ?? '',
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
            elevationMinM: $model->elevation_min_m !== null ? (float) $model->elevation_min_m : null,
            biome: $model->biome,
            habitat: $model->habitat,
            identificadores: $model->identificadores
                ->map(fn ($identificador) => IdentificadorEspecimen::crear($identificador->tipo, $identificador->valor))
                ->all(),
            taxonVerbatim: $model->taxon_verbatim,
            muestraId: $model->muestra_id,
            localidadId: $model->localidad_id,
            localidadVerbatim: $model->localidad_verbatim,
            fechaVerbatim: $model->fecha_verbatim,
            fechaColectaFin: $fechaColectaFin,
            individualCountVerbatim: $model->individual_count_verbatim,
            sex: $model->sex,
            lifeStage: $model->life_stage,
            caste: $model->caste,
            typeStatus: $model->type_status,
            coordVerbatim: $model->coord_verbatim,
            elevationMaxM: $model->elevation_max_m !== null ? (float) $model->elevation_max_m : null,
            microhabitat: $model->microhabitat,
            biogeographicRegion: $model->biogeographic_region,
            endemic: $model->endemic !== null ? (bool) $model->endemic : null,
            dnaNotes: $model->dna_notes,
            occurrenceRemarks: $model->occurrence_remarks,
            taxonomicNotes: $model->taxonomic_notes,
            actaRecepcion: $model->acta_recepcion,
            estadoRevision: $model->estado_revision !== null
                ? EstadoRevision::from($model->estado_revision)
                : EstadoRevision::porDefecto(),
            motivoRevision: $model->motivo_revision,
            filaOrigenExcel: $model->fila_origen_excel !== null ? (int) $model->fila_origen_excel : null,
        );
    }
}
