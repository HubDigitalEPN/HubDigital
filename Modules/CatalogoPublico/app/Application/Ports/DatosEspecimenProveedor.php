<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\Ports;

final readonly class DatosEspecimenProveedor
{
    public function __construct(
        public string $especimenId,
        public string $occurrenceId,
        public string $scientificName,
        public int $individualCount,
        public ?string $typeStatus,
        public ?string $typeNotes,
        public ?string $specimenNotes,
        public ?string $samplingProtocol,
        public ?string $recordedBy,
        public string $occurrenceStatus,
        public ?string $family,
        public ?string $genus,
        public ?string $country,
        public ?string $localityName,
        public ?float $decimalLatitude,
        public ?float $decimalLongitude,
        public ?string $stateProvince = null,
        public ?string $eventDate = null,
        public ?string $caste = null,
        public ?string $lifeStage = null,
        public ?float $elevationMinM = null,
        public ?float $elevationMaxM = null,
    ) {}
}
