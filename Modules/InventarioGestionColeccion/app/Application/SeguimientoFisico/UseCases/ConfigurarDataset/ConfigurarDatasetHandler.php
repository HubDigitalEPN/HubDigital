<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfigurarDataset;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DatasetConfig;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\DatasetConfigRepositoryInterface;

final class ConfigurarDatasetHandler
{
    public function __construct(
        private readonly DatasetConfigRepositoryInterface $datasetConfigRepo,
    ) {}

    public function handle(ConfigurarDatasetInput $input): ConfigurarDatasetOutput
    {
        $actual = $this->datasetConfigRepo->obtenerActual();
        $creado = $actual === null;

        if ($creado) {
            $datasetConfig = DatasetConfig::crear(
                id: $this->datasetConfigRepo->nextIdentity(),
                institutionCode: $input->institutionCode,
                collectionCode: $input->collectionCode,
                basisOfRecord: $input->basisOfRecord,
                institutionId: $input->institutionId,
                collectionId: $input->collectionId,
                datasetName: $input->datasetName,
                ownerInstitutionCode: $input->ownerInstitutionCode,
                rightsHolder: $input->rightsHolder,
                accessRights: $input->accessRights,
                license: $input->license,
                informationWithheld: $input->informationWithheld,
                emlContacto: $input->emlContacto,
                emlTitulo: $input->emlTitulo,
            );
        } else {
            $datasetConfig = $actual;
            $datasetConfig->actualizar(
                institutionCode: $input->institutionCode,
                collectionCode: $input->collectionCode,
                basisOfRecord: $input->basisOfRecord,
                institutionId: $input->institutionId,
                collectionId: $input->collectionId,
                datasetName: $input->datasetName,
                ownerInstitutionCode: $input->ownerInstitutionCode,
                rightsHolder: $input->rightsHolder,
                accessRights: $input->accessRights,
                license: $input->license,
                informationWithheld: $input->informationWithheld,
                emlContacto: $input->emlContacto,
                emlTitulo: $input->emlTitulo,
            );
        }

        $this->datasetConfigRepo->guardar($datasetConfig);

        return new ConfigurarDatasetOutput(
            id: $datasetConfig->id(),
            institutionCode: $datasetConfig->institutionCode()->value,
            collectionCode: $datasetConfig->collectionCode()->value,
            basisOfRecord: $datasetConfig->basisOfRecord()->value,
            institutionId: $datasetConfig->institutionId(),
            collectionId: $datasetConfig->collectionId(),
            datasetName: $datasetConfig->datasetName(),
            ownerInstitutionCode: $datasetConfig->ownerInstitutionCode(),
            rightsHolder: $datasetConfig->rightsHolder(),
            accessRights: $datasetConfig->accessRights(),
            license: $datasetConfig->license()?->value,
            informationWithheld: $datasetConfig->informationWithheld(),
            emlContacto: $datasetConfig->emlContacto(),
            emlTitulo: $datasetConfig->emlTitulo(),
            creado: $creado,
        );
    }
}
