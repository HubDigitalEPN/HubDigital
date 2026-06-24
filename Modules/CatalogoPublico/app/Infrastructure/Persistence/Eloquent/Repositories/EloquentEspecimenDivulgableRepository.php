<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenDivulgableId;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models\EspecimenDivulgableEloquentModel;

final class EloquentEspecimenDivulgableRepository implements EspecimenDivulgableRepositoryInterface
{
    public function nextIdentity(): EspecimenDivulgableId
    {
        return EspecimenDivulgableId::generate();
    }

    public function guardar(EspecimenDivulgable $divulgable): void
    {
        $flags = $divulgable->configuracion()->toArray();

        EspecimenDivulgableEloquentModel::updateOrCreate(
            ['id' => $divulgable->id()->toString()],
            [
                'especimen_id' => $divulgable->especimenId(),
                'occurrence_id_visible' => $flags['occurrenceIDVisible'],
                'scientific_name_visible' => $flags['scientificNameVisible'],
                'individual_count_visible' => $flags['individualCountVisible'],
                'type_status_visible' => $flags['typeStatusVisible'],
                'type_notes_visible' => $flags['typeNotesVisible'],
                'specimen_notes_visible' => $flags['specimenNotesVisible'],
                'sampling_protocol_visible' => $flags['samplingProtocolVisible'],
                'recorded_by_visible' => $flags['recordedByVisible'],
                'occurrence_status_visible' => $flags['occurrenceStatusVisible'],
                'family_visible' => $flags['familyVisible'],
                'genus_visible' => $flags['genusVisible'],
                'country_visible' => $flags['countryVisible'],
                'state_province_visible' => $flags['stateProvinceVisible'],
                'locality_name_visible' => $flags['localityNameVisible'],
                'decimal_latitude_visible' => $flags['decimalLatitudeVisible'],
                'decimal_longitude_visible' => $flags['decimalLongitudeVisible'],
                'elevation_visible' => $flags['elevationVisible'],
                'event_date_visible' => $flags['eventDateVisible'],
                'caste_visible' => $flags['casteVisible'],
                'life_stage_visible' => $flags['lifeStageVisible'],
            ]
        );
    }

    public function buscarPorOccurrenceID(string $occurrenceID): ?EspecimenDivulgable
    {
        // JOIN con taxonomia.especimenes para resolver occurrence_id → especimen_id (FK estable)
        $model = EspecimenDivulgableEloquentModel::query()
            ->join(
                'taxonomia.especimenes',
                'taxonomia.especimenes.id',
                '=',
                'divulgacion.especimenes_divulgables.especimen_id'
            )
            ->where('taxonomia.especimenes.occurrence_id', $occurrenceID)
            ->select('divulgacion.especimenes_divulgables.*')
            ->first();

        if ($model === null) {
            return null;
        }

        return EspecimenDivulgable::reconstituir(
            id: EspecimenDivulgableId::fromString($model->id),
            especimenId: $model->especimen_id,
            configuracion: $this->buildConfiguracion($model),
        );
    }

    /**
     * @param  list<string>  $occurrenceIDs
     * @return list<EspecimenDivulgable>
     */
    public function buscarPorOccurrenceIDs(array $occurrenceIDs): array
    {
        if ($occurrenceIDs === []) {
            return [];
        }

        $models = EspecimenDivulgableEloquentModel::query()
            ->join(
                'taxonomia.especimenes',
                'taxonomia.especimenes.id',
                '=',
                'divulgacion.especimenes_divulgables.especimen_id'
            )
            ->whereIn('taxonomia.especimenes.occurrence_id', $occurrenceIDs)
            ->select('divulgacion.especimenes_divulgables.*')
            ->get();

        return $models->map(fn (EspecimenDivulgableEloquentModel $model) => EspecimenDivulgable::reconstituir(
            id: EspecimenDivulgableId::fromString($model->id),
            especimenId: $model->especimen_id,
            configuracion: $this->buildConfiguracion($model),
        ))->values()->all();
    }

    private function buildConfiguracion(EspecimenDivulgableEloquentModel $model): ConfiguracionVisibilidad
    {
        return ConfiguracionVisibilidad::desde([
            'occurrenceIDVisible' => (bool) $model->occurrence_id_visible,
            'scientificNameVisible' => (bool) $model->scientific_name_visible,
            'individualCountVisible' => (bool) $model->individual_count_visible,
            'typeStatusVisible' => (bool) $model->type_status_visible,
            'typeNotesVisible' => (bool) $model->type_notes_visible,
            'specimenNotesVisible' => (bool) $model->specimen_notes_visible,
            'samplingProtocolVisible' => (bool) $model->sampling_protocol_visible,
            'recordedByVisible' => (bool) $model->recorded_by_visible,
            'occurrenceStatusVisible' => (bool) $model->occurrence_status_visible,
            'familyVisible' => (bool) $model->family_visible,
            'genusVisible' => (bool) $model->genus_visible,
            'countryVisible' => (bool) $model->country_visible,
            'stateProvinceVisible' => (bool) $model->state_province_visible,
            'localityNameVisible' => (bool) $model->locality_name_visible,
            'decimalLatitudeVisible' => (bool) $model->decimal_latitude_visible,
            'decimalLongitudeVisible' => (bool) $model->decimal_longitude_visible,
            'elevationVisible' => (bool) $model->elevation_visible,
            'eventDateVisible' => (bool) $model->event_date_visible,
            'casteVisible' => (bool) $model->caste_visible,
            'lifeStageVisible' => (bool) $model->life_stage_visible,
        ]);
    }
}
