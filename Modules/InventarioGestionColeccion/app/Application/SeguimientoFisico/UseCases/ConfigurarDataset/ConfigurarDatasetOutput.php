<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfigurarDataset;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\DatasetConfigId;

final readonly class ConfigurarDatasetOutput
{
    public function __construct(
        public DatasetConfigId $id,
        public string $institutionCode,
        public string $collectionCode,
        public string $basisOfRecord,
        public ?string $institutionId,
        public ?string $collectionId,
        public ?string $datasetName,
        public ?string $ownerInstitutionCode,
        public ?string $rightsHolder,
        public ?string $accessRights,
        public ?string $license,
        public ?string $informationWithheld,
        public ?string $emlContacto,
        public ?string $emlTitulo,
        public bool $creado,
    ) {}
}
