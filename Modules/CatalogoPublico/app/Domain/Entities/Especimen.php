<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\Entities;

use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenId;
use InvalidArgumentException;
final class Especimen
{
    private function __construct(
        private readonly EspecimenId $id,
        private readonly string $occurrenceID,
        private readonly string $scientificName,
        private readonly int $individualCount,
        private readonly ?string $typeStatus,
        private readonly ?string $typeNotes,
        private readonly ?string $specimenNotes,
        private readonly ?string $samplingProtocol,
        private readonly ?string $recordedBy,
        private readonly string $occurrenceStatus,
        private readonly ?string $family,
        private readonly ?string $genus,
        private readonly ?string $country,
        private readonly ?string $localityName,
        private readonly ?float $decimalLatitude,
        private readonly ?float $decimalLongitude,
    ) {}

    public static function registrar(
        EspecimenId $id,
        string $occurrenceID,
        string $scientificName,
        int $individualCount,
        ?string $typeStatus,
        ?string $typeNotes,
        ?string $specimenNotes,
        ?string $samplingProtocol,
        ?string $recordedBy,
        string $occurrenceStatus,
        ?string $family,
        ?string $genus,
        ?string $country,
        ?string $localityName,
        ?float $decimalLatitude,
        ?float $decimalLongitude,
    ): self {
        if ($occurrenceID === '') {
            throw new InvalidArgumentException('El occurrenceID no puede ser vacío');
        }

        if ($scientificName === '') {
            throw new InvalidArgumentException('El scientificName no puede ser vacío');
        }

        if ($occurrenceStatus === '') {
            throw new InvalidArgumentException('El occurrenceStatus no puede ser vacío');
        }

        if ($individualCount < 0) {
            throw new InvalidArgumentException('El individualCount no puede ser negativo');
        }

        return new self(
            id: $id,
            occurrenceID: $occurrenceID,
            scientificName: $scientificName,
            individualCount: $individualCount,
            typeStatus: $typeStatus,
            typeNotes: $typeNotes,
            specimenNotes: $specimenNotes,
            samplingProtocol: $samplingProtocol,
            recordedBy: $recordedBy,
            occurrenceStatus: $occurrenceStatus,
            family: $family,
            genus: $genus,
            country: $country,
            localityName: $localityName,
            decimalLatitude: $decimalLatitude,
            decimalLongitude: $decimalLongitude,
        );
    }

    public function id(): EspecimenId
    {
        return $this->id;
    }

    public function occurrenceID(): string
    {
        return $this->occurrenceID;
    }

    public function scientificName(): string
    {
        return $this->scientificName;
    }

    public function individualCount(): int
    {
        return $this->individualCount;
    }

    public function typeStatus(): ?string
    {
        return $this->typeStatus;
    }

    public function typeNotes(): ?string
    {
        return $this->typeNotes;
    }

    public function specimenNotes(): ?string
    {
        return $this->specimenNotes;
    }

    public function samplingProtocol(): ?string
    {
        return $this->samplingProtocol;
    }

    public function recordedBy(): ?string
    {
        return $this->recordedBy;
    }

    public function occurrenceStatus(): string
    {
        return $this->occurrenceStatus;
    }

    public function family(): ?string
    {
        return $this->family;
    }

    public function genus(): ?string
    {
        return $this->genus;
    }

    public function country(): ?string
    {
        return $this->country;
    }

    public function localityName(): ?string
    {
        return $this->localityName;
    }

    public function decimalLatitude(): ?float
    {
        return $this->decimalLatitude;
    }

    public function decimalLongitude(): ?float
    {
        return $this->decimalLongitude;
    }

    public static function reconstituir(
        EspecimenId $id,
        string $occurrenceID,
        string $scientificName,
        int $individualCount,
        ?string $typeStatus,
        ?string $typeNotes,
        ?string $specimenNotes,
        ?string $samplingProtocol,
        ?string $recordedBy,
        string $occurrenceStatus,
        ?string $family,
        ?string $genus,
        ?string $country,
        ?string $localityName,
        ?float $decimalLatitude,
        ?float $decimalLongitude,
    ): self {
        return new self(
            id: $id,
            occurrenceID: $occurrenceID,
            scientificName: $scientificName,
            individualCount: $individualCount,
            typeStatus: $typeStatus,
            typeNotes: $typeNotes,
            specimenNotes: $specimenNotes,
            samplingProtocol: $samplingProtocol,
            recordedBy: $recordedBy,
            occurrenceStatus: $occurrenceStatus,
            family: $family,
            genus: $genus,
            country: $country,
            localityName: $localityName,
            decimalLatitude: $decimalLatitude,
            decimalLongitude: $decimalLongitude,
        );
    }
}
