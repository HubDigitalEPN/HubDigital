<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Repositories;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DatasetConfig;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\DatasetConfigRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\BasisOfRecord;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CollectionCode;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\DatasetConfigId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\InstitutionCode;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\License;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\DatasetConfigEloquentModel;

class EloquentDatasetConfigRepository implements DatasetConfigRepositoryInterface
{
    public function nextIdentity(): DatasetConfigId
    {
        return DatasetConfigId::generar();
    }

    public function guardar(DatasetConfig $datasetConfig): void
    {
        DatasetConfigEloquentModel::updateOrCreate(
            ['id' => (string) $datasetConfig->id()],
            [
                'institution_code' => $datasetConfig->institutionCode()->value,
                'collection_code' => $datasetConfig->collectionCode()->value,
                'institution_id' => $datasetConfig->institutionId(),
                'collection_id' => $datasetConfig->collectionId(),
                'dataset_name' => $datasetConfig->datasetName(),
                'owner_institution_code' => $datasetConfig->ownerInstitutionCode(),
                'rights_holder' => $datasetConfig->rightsHolder(),
                'access_rights' => $datasetConfig->accessRights(),
                'license' => $datasetConfig->license()?->value,
                'basis_of_record' => $datasetConfig->basisOfRecord()->value,
                'information_withheld' => $datasetConfig->informationWithheld(),
                'eml_contacto' => $datasetConfig->emlContacto(),
                'eml_titulo' => $datasetConfig->emlTitulo(),
            ]
        );
    }

    public function obtenerActual(): ?DatasetConfig
    {
        $model = DatasetConfigEloquentModel::orderBy('created_at', 'desc')->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorId(DatasetConfigId $id): ?DatasetConfig
    {
        $model = DatasetConfigEloquentModel::find((string) $id);

        return $model ? $this->toDomain($model) : null;
    }

    private function toDomain(DatasetConfigEloquentModel $model): DatasetConfig
    {
        return DatasetConfig::reconstituir(
            id: DatasetConfigId::desde($model->id),
            institutionCode: InstitutionCode::desde($model->institution_code),
            collectionCode: CollectionCode::desde($model->collection_code),
            basisOfRecord: BasisOfRecord::from($model->basis_of_record),
            institutionId: $model->institution_id,
            collectionId: $model->collection_id,
            datasetName: $model->dataset_name,
            ownerInstitutionCode: $model->owner_institution_code,
            rightsHolder: $model->rights_holder,
            accessRights: $model->access_rights,
            license: $model->license !== null ? License::desde($model->license) : null,
            informationWithheld: $model->information_withheld,
            emlContacto: $model->eml_contacto,
            emlTitulo: $model->eml_titulo,
        );
    }
}
