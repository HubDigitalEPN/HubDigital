<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class RegistroExportable
{
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
        public readonly ?string $stateProvince,
        public readonly ?string $minimumElevationInMeters,
        public readonly ?string $maximumElevationInMeters,
        public readonly ?string $eventDate,
        public readonly ?string $caste,
        public readonly ?string $lifeStage,
        public readonly ?string $disposition,
        public readonly ?string $georeferenceRemarks,
        public readonly ?string $localityExcel,
        public readonly ?string $localityInec,
        public readonly ?string $localityInecReference,
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
        ?string $stateProvince,
        ?float $elevationMinM,
        ?float $elevationMaxM,
        ?string $eventDate,
        ?string $caste,
        ?string $lifeStage,
        ConfiguracionVisibilidad $visibilidad,
        ?string $disposition = null,
        ?string $georeferenceRemarks = null,
        ?string $localityExcel = null,
        ?string $localityInec = null,
        ?string $localityInecReference = null,
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
        $coordenadasVisibles = $visibilidad->decimalLatitudeVisible && $visibilidad->decimalLongitudeVisible;
        $latitud = NumeroExportacion::decimal($decimalLatitude, -90, 90);
        $longitud = NumeroExportacion::decimal($decimalLongitude, -180, 180);
        $parValido = $coordenadasVisibles && $latitud !== null && $longitud !== null;

        return new self(
            occurrenceID: $visibilidad->occurrenceIDVisible ? $occurrenceID : '',
            scientificName: $visibilidad->scientificNameVisible ? $scientificName : '',
            typeStatus: $aplicar($visibilidad->typeStatusVisible, $typeStatus),
            occurrenceStatus: $aplicar($visibilidad->occurrenceStatusVisible, $occurrenceStatus),
            individualCount: $aplicar($visibilidad->individualCountVisible, $individualCount),
            localityName: $aplicar($visibilidad->localityNameVisible, $localityName),
            country: $aplicar($visibilidad->countryVisible, $country),
            decimalLatitude: $parValido ? $latitud : null,
            decimalLongitude: $parValido ? $longitud : null,
            recordedBy: $aplicar($visibilidad->recordedByVisible, $recordedBy),
            samplingProtocol: $aplicar($visibilidad->samplingProtocolVisible, $samplingProtocol),
            typeNotes: $aplicar($visibilidad->typeNotesVisible, $typeNotes),
            specimenNotes: $aplicar($visibilidad->specimenNotesVisible, $specimenNotes),
            stateProvince: $aplicar($visibilidad->stateProvinceVisible, $stateProvince),
            minimumElevationInMeters: $aplicar($visibilidad->elevationVisible, $elevationMinM),
            maximumElevationInMeters: $aplicar($visibilidad->elevationVisible, $elevationMaxM),
            eventDate: $aplicar($visibilidad->eventDateVisible, $eventDate),
            caste: $aplicar($visibilidad->casteVisible, $caste),
            lifeStage: $aplicar($visibilidad->lifeStageVisible, $lifeStage),
            // Mantiene la barrera de divulgación que protegía disposition en el perfil anterior.
            disposition: $aplicar($visibilidad->typeStatusVisible, $disposition),
            georeferenceRemarks: $aplicar($coordenadasVisibles, $georeferenceRemarks),
            localityExcel: $aplicar($visibilidad->localityNameVisible, $localityExcel),
            localityInec: $aplicar($visibilidad->localityNameVisible, $localityInec),
            localityInecReference: $aplicar($visibilidad->localityNameVisible, $localityInecReference),
        );
    }

    /** @return list<string> */
    public static function encabezados(): array
    {
        return PerfilExportacionPublica::ENCABEZADOS_XLSX;
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
            'stateProvince' => $this->stateProvince ?? '',
            'minimumElevationInMeters' => $this->minimumElevationInMeters ?? '',
            'maximumElevationInMeters' => $this->maximumElevationInMeters ?? '',
            'eventDate' => $this->eventDate ?? '',
            'caste' => $this->caste ?? '',
            'lifeStage' => $this->lifeStage ?? '',
            'disposition' => $this->disposition ?? '',
            'georeferenceRemarks' => $this->georeferenceRemarks ?? '',
            // No hay una distancia métrica curada en la fuente. Una nota no permite inferirla.
            'coordinateUncertaintyInMeters' => '',
            'localityExcel' => $this->localityExcel ?? '',
            'localityInec' => $this->localityInec ?? '',
            'localityInecReference' => $this->localityInecReference ?? '',
            'exportProfile' => PerfilExportacionPublica::IDENTIFICADOR,
        ];
    }
}
