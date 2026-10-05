<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

/** Esquema versionado de las descargas públicas; no implica un archivo Darwin Core completo. */
final class PerfilExportacionPublica
{
    public const string IDENTIFICADOR = 'hubdigital.portal-publico/2.0';

    public const array ENCABEZADOS_CSV = [
        'N.º catálogo', 'Taxón', 'Fecha', 'Localidad del Excel', 'Localidad INEC', 'Código INEC',
        'Provincia', 'Latitud', 'Longitud', 'Precisión', 'Tipo', 'Referencia INEC',
        'Disposición', 'Perfil de exportación', 'Localidad',
    ];

    public const array ENCABEZADOS_XLSX = [
        'occurrenceID', 'scientificName', 'typeStatus', 'occurrenceStatus', 'individualCount',
        'localityName', 'country', 'decimalLatitude', 'decimalLongitude', 'recordedBy',
        'samplingProtocol', 'typeNotes', 'specimenNotes', 'stateProvince',
        'minimumElevationInMeters', 'maximumElevationInMeters', 'eventDate', 'caste', 'lifeStage',
        'disposition', 'georeferenceRemarks', 'coordinateUncertaintyInMeters',
        'localityExcel', 'localityInec', 'localityInecReference', 'exportProfile',
    ];
}
