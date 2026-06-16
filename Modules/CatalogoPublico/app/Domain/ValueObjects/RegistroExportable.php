<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class RegistroExportable
{
    private const array ENCABEZADOS = [
        'occurrenceID',
        'scientificName',
        'typeStatus',
        'occurrenceStatus',
        'individualCount',
        'localityName',
        'country',
        'decimalLatitude',
        'decimalLongitude',
        'recordedBy',
        'samplingProtocol',
        'typeNotes',
        'specimenNotes',
    ];

    private function __construct(
        public readonly string $occurrenceID,
        public readonly string $scientificName,
        public readonly ?string $typeStatus,
        public readonly ?string $occurrenceStatus,
        public readonly ?string $individualCount,
        public readonly ?string $localityName,
        public readonly ?string $country,
        public readonly ?string $decimalLatitude,
        public readonly ?string $decimalLongitude,
        public readonly ?string $recordedBy,
        public readonly ?string $samplingProtocol,
        public readonly ?string $typeNotes,
        public readonly ?string $specimenNotes,
    ) {}

    public static function desde(
        string $occurrenceID,
        string $scientificName,
        ?string $typeStatus,
        ?string $occurrenceStatus,
        ?int $individualCount,
        ?string $localityName,
        ?string $country,
        ?float $decimalLatitude,
        ?float $decimalLongitude,
        ?string $recordedBy,
        ?string $samplingProtocol,
        ?string $typeNotes,
        ?string $specimenNotes,
        ConfiguracionVisibilidad $visibilidad,
    ): self {
        if (trim($occurrenceID) === '') {
            throw new InvalidArgumentException('El occurrenceID no puede estar vacío en un registro exportable.');
        }

        if (trim($scientificName) === '') {
            throw new InvalidArgumentException('El scientificName no puede estar vacío en un registro exportable.');
        }

        $aplicar = static fn (bool $visible, mixed $valor): ?string => ($visible && $valor !== null && $valor !== '')
            ? (string) $valor
            : null;

        return new self(
            occurrenceID: $occurrenceID,
            scientificName: $scientificName,
            typeStatus: $aplicar($visibilidad->typeStatusVisible, $typeStatus),
            occurrenceStatus: $aplicar($visibilidad->occurrenceStatusVisible, $occurrenceStatus),
            individualCount: $aplicar($visibilidad->individualCountVisible, $individualCount),
            localityName: $aplicar($visibilidad->localityNameVisible, $localityName),
            country: $aplicar($visibilidad->countryVisible, $country),
            decimalLatitude: $aplicar($visibilidad->decimalLatitudeVisible, $decimalLatitude),
            decimalLongitude: $aplicar($visibilidad->decimalLongitudeVisible, $decimalLongitude),
            recordedBy: $aplicar($visibilidad->recordedByVisible, $recordedBy),
            samplingProtocol: $aplicar($visibilidad->samplingProtocolVisible, $samplingProtocol),
            typeNotes: $aplicar($visibilidad->typeNotesVisible, $typeNotes),
            specimenNotes: $aplicar($visibilidad->specimenNotesVisible, $specimenNotes),
        );
    }

    /** @return list<string> */
    public static function encabezados(): array
    {
        return self::ENCABEZADOS;
    }

    /** @return array<string, string> null → '' para celdas vacías en el XLSX */
    public function toArray(): array
    {
        return [
            'occurrenceID' => $this->occurrenceID,
            'scientificName' => $this->scientificName,
            'typeStatus' => $this->typeStatus ?? '',
            'occurrenceStatus' => $this->occurrenceStatus ?? '',
            'individualCount' => $this->individualCount ?? '',
            'localityName' => $this->localityName ?? '',
            'country' => $this->country ?? '',
            'decimalLatitude' => $this->decimalLatitude ?? '',
            'decimalLongitude' => $this->decimalLongitude ?? '',
            'recordedBy' => $this->recordedBy ?? '',
            'samplingProtocol' => $this->samplingProtocol ?? '',
            'typeNotes' => $this->typeNotes ?? '',
            'specimenNotes' => $this->specimenNotes ?? '',
        ];
    }
}
