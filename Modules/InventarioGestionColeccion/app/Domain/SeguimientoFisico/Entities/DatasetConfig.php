<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\BasisOfRecord;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CollectionCode;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\DatasetConfigId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\InstitutionCode;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\License;

class DatasetConfig
{
    private function __construct(
        private readonly DatasetConfigId $id,
        private InstitutionCode $institutionCode,
        private CollectionCode $collectionCode,
        private BasisOfRecord $basisOfRecord,
        private ?string $institutionId,
        private ?string $collectionId,
        private ?string $datasetName,
        private ?string $ownerInstitutionCode,
        private ?string $rightsHolder,
        private ?string $accessRights,
        private ?License $license,
        private ?string $informationWithheld,
        private ?string $emlContacto,
        private ?string $emlTitulo,
    ) {}

    public static function crear(
        DatasetConfigId $id,
        string $institutionCode,
        string $collectionCode,
        ?string $basisOfRecord = null,
        ?string $institutionId = null,
        ?string $collectionId = null,
        ?string $datasetName = null,
        ?string $ownerInstitutionCode = null,
        ?string $rightsHolder = null,
        ?string $accessRights = null,
        ?string $license = null,
        ?string $informationWithheld = null,
        ?string $emlContacto = null,
        ?string $emlTitulo = null,
    ): self {
        return new self(
            id: $id,
            institutionCode: InstitutionCode::desde($institutionCode),
            collectionCode: CollectionCode::desde($collectionCode),
            basisOfRecord: $basisOfRecord !== null
                ? BasisOfRecord::from($basisOfRecord)
                : BasisOfRecord::porDefecto(),
            institutionId: self::normalizarOpcional($institutionId),
            collectionId: self::normalizarOpcional($collectionId),
            datasetName: self::normalizarOpcional($datasetName),
            ownerInstitutionCode: self::normalizarOpcional($ownerInstitutionCode),
            rightsHolder: self::normalizarOpcional($rightsHolder),
            accessRights: self::normalizarOpcional($accessRights),
            license: $license !== null && trim($license) !== '' ? License::desde($license) : null,
            informationWithheld: self::normalizarOpcional($informationWithheld),
            emlContacto: self::normalizarOpcional($emlContacto),
            emlTitulo: self::normalizarOpcional($emlTitulo),
        );
    }

    public static function reconstituir(
        DatasetConfigId $id,
        InstitutionCode $institutionCode,
        CollectionCode $collectionCode,
        BasisOfRecord $basisOfRecord,
        ?string $institutionId,
        ?string $collectionId,
        ?string $datasetName,
        ?string $ownerInstitutionCode,
        ?string $rightsHolder,
        ?string $accessRights,
        ?License $license,
        ?string $informationWithheld,
        ?string $emlContacto,
        ?string $emlTitulo,
    ): self {
        return new self(
            id: $id,
            institutionCode: $institutionCode,
            collectionCode: $collectionCode,
            basisOfRecord: $basisOfRecord,
            institutionId: $institutionId,
            collectionId: $collectionId,
            datasetName: $datasetName,
            ownerInstitutionCode: $ownerInstitutionCode,
            rightsHolder: $rightsHolder,
            accessRights: $accessRights,
            license: $license,
            informationWithheld: $informationWithheld,
            emlContacto: $emlContacto,
            emlTitulo: $emlTitulo,
        );
    }

    public function actualizar(
        string $institutionCode,
        string $collectionCode,
        ?string $basisOfRecord = null,
        ?string $institutionId = null,
        ?string $collectionId = null,
        ?string $datasetName = null,
        ?string $ownerInstitutionCode = null,
        ?string $rightsHolder = null,
        ?string $accessRights = null,
        ?string $license = null,
        ?string $informationWithheld = null,
        ?string $emlContacto = null,
        ?string $emlTitulo = null,
    ): void {
        $this->institutionCode = InstitutionCode::desde($institutionCode);
        $this->collectionCode = CollectionCode::desde($collectionCode);
        $this->basisOfRecord = $basisOfRecord !== null
            ? BasisOfRecord::from($basisOfRecord)
            : BasisOfRecord::porDefecto();
        $this->institutionId = self::normalizarOpcional($institutionId);
        $this->collectionId = self::normalizarOpcional($collectionId);
        $this->datasetName = self::normalizarOpcional($datasetName);
        $this->ownerInstitutionCode = self::normalizarOpcional($ownerInstitutionCode);
        $this->rightsHolder = self::normalizarOpcional($rightsHolder);
        $this->accessRights = self::normalizarOpcional($accessRights);
        $this->license = $license !== null && trim($license) !== '' ? License::desde($license) : null;
        $this->informationWithheld = self::normalizarOpcional($informationWithheld);
        $this->emlContacto = self::normalizarOpcional($emlContacto);
        $this->emlTitulo = self::normalizarOpcional($emlTitulo);
    }

    private static function normalizarOpcional(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $trim = trim($valor);

        return $trim === '' ? null : $trim;
    }

    public function id(): DatasetConfigId
    {
        return $this->id;
    }

    public function institutionCode(): InstitutionCode
    {
        return $this->institutionCode;
    }

    public function collectionCode(): CollectionCode
    {
        return $this->collectionCode;
    }

    public function basisOfRecord(): BasisOfRecord
    {
        return $this->basisOfRecord;
    }

    public function institutionId(): ?string
    {
        return $this->institutionId;
    }

    public function collectionId(): ?string
    {
        return $this->collectionId;
    }

    public function datasetName(): ?string
    {
        return $this->datasetName;
    }

    public function ownerInstitutionCode(): ?string
    {
        return $this->ownerInstitutionCode;
    }

    public function rightsHolder(): ?string
    {
        return $this->rightsHolder;
    }

    public function accessRights(): ?string
    {
        return $this->accessRights;
    }

    public function license(): ?License
    {
        return $this->license;
    }

    public function informationWithheld(): ?string
    {
        return $this->informationWithheld;
    }

    public function emlContacto(): ?string
    {
        return $this->emlContacto;
    }

    public function emlTitulo(): ?string
    {
        return $this->emlTitulo;
    }
}
