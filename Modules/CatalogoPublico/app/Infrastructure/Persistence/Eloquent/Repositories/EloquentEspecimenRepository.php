<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\CatalogoPublico\Domain\Entities\Especimen;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenId;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models\EspecimenEloquentModel;

final class EloquentEspecimenRepository implements EspecimenRepositoryInterface
{
    public function nextIdentity(): EspecimenId
    {
        return EspecimenId::generate();
    }

    public function guardar(Especimen $especimen): void
    {
        EspecimenEloquentModel::updateOrCreate(
            ['id' => $especimen->id()->toString()],
            [
                'occurrence_id' => $especimen->occurrenceID(),
                'scientific_name' => $especimen->scientificName(),
                'individual_count' => $especimen->individualCount(),
                'type_status' => $especimen->typeStatus(),
                'type_notes' => $especimen->typeNotes(),
                'specimen_notes' => $especimen->specimenNotes(),
                'sampling_protocol' => $especimen->samplingProtocol(),
                'recorded_by' => $especimen->recordedBy(),
                'occurrence_status' => $especimen->occurrenceStatus(),
                'family' => $especimen->family(),
                'genus' => $especimen->genus(),
                'country' => $especimen->country(),
                'locality_name' => $especimen->localityName(),
                'decimal_latitude' => $especimen->decimalLatitude(),
                'decimal_longitude' => $especimen->decimalLongitude(),
            ]
        );
    }

    public function buscarPorOccurrenceID(string $occurrenceID): ?Especimen
    {
        $model = EspecimenEloquentModel::where('occurrence_id', $occurrenceID)->first();

        if ($model === null) {
            return null;
        }

        return Especimen::reconstituir(
            id: EspecimenId::fromString($model->id),
            occurrenceID: $model->occurrence_id,
            scientificName: $model->scientific_name,
            individualCount: (int) $model->individual_count,
            typeStatus: $model->type_status,
            typeNotes: $model->type_notes,
            specimenNotes: $model->specimen_notes,
            samplingProtocol: $model->sampling_protocol,
            recordedBy: $model->recorded_by,
            occurrenceStatus: $model->occurrence_status,
            family: $model->family,
            genus: $model->genus,
            country: $model->country,
            localityName: $model->locality_name,
            decimalLatitude: $model->decimal_latitude !== null ? (float) $model->decimal_latitude : null,
            decimalLongitude: $model->decimal_longitude !== null ? (float) $model->decimal_longitude : null,
        );
    }
}
