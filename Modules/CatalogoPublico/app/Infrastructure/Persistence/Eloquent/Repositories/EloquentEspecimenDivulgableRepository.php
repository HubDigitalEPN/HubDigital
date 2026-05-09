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
                'occurrence_id' => $divulgable->occurrenceID(),
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
                'locality_name_visible' => $flags['localityNameVisible'],
                'decimal_latitude_visible' => $flags['decimalLatitudeVisible'],
                'decimal_longitude_visible' => $flags['decimalLongitudeVisible'],
            ]
        );
    }

    public function buscarPorOccurrenceID(string $occurrenceID): ?EspecimenDivulgable
    {
        $model = EspecimenDivulgableEloquentModel::where('occurrence_id', $occurrenceID)->first();

        if ($model === null) {
            return null;
        }

        $configuracion = ConfiguracionVisibilidad::desde([
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
            'localityNameVisible' => (bool) $model->locality_name_visible,
            'decimalLatitudeVisible' => (bool) $model->decimal_latitude_visible,
            'decimalLongitudeVisible' => (bool) $model->decimal_longitude_visible,
        ]);

        return EspecimenDivulgable::reconstituir(
            id: EspecimenDivulgableId::fromString($model->id),
            occurrenceID: $model->occurrence_id,
            configuracion: $configuracion,
        );
    }
}
