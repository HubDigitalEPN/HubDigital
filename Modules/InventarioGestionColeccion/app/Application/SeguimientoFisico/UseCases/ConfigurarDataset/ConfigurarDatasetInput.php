<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfigurarDataset;

final readonly class ConfigurarDatasetInput
{
    public function __construct(
        public string $institutionCode,
        public string $collectionCode,
        public ?string $basisOfRecord = null,
        public ?string $institutionId = null,
        public ?string $collectionId = null,
        public ?string $datasetName = null,
        public ?string $ownerInstitutionCode = null,
        public ?string $rightsHolder = null,
        public ?string $accessRights = null,
        public ?string $license = null,
        public ?string $informationWithheld = null,
        public ?string $emlContacto = null,
        public ?string $emlTitulo = null,
    ) {}
}
