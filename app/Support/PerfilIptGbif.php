<?php

declare(strict_types=1);

namespace App\Support;

use Modules\CatalogoPublico\Domain\ValueObjects\EstadoOcurrenciaPublico;

/** Perfil de intercambio del ejemplo EPN: no contiene sus registros ni sus metadatos. */
final class PerfilIptGbif
{
    public const COLUMNAS = [
        'id', 'type', 'language', 'license', 'rightsHolder', 'accessRights',
        'institutionID', 'collectionID', 'institutionCode', 'collectionCode',
        'datasetName', 'ownerInstitutionCode', 'basisOfRecord', 'occurrenceID',
        'catalogNumber', 'recordNumber', 'recordedBy', 'individualCount', 'sex',
        'lifeStage', 'caste', 'occurrenceStatus', 'otherCatalogNumbers',
        'occurrenceRemarks', 'preparations', 'disposition', 'eventDate', 'eventTime',
        'year', 'month', 'day', 'habitat', 'samplingProtocol', 'continent', 'country',
        'countryCode', 'stateProvince', 'municipality', 'verbatimLocality',
        'minimumElevationInMeters', 'maximumElevationInMeters', 'verbatimElevation',
        'verbatimDepth', 'decimalLatitude', 'decimalLongitude', 'geodeticDatum',
        'verbatimLatitude', 'verbatimLongitude', 'verbatimCoordinateSystem',
        'verbatimSRS', 'identificationQualifier', 'identifiedBy',
        'identificationRemarks', 'taxonID', 'scientificName', 'kingdom', 'phylum',
        'class', 'order', 'family', 'subfamily', 'tribe', 'genus', 'specificEpithet',
        'infraspecificEpithet', 'taxonRank', 'scientificNameAuthorship',
        'vernacularName', 'nomenclaturalCode',
    ];

    public static function fila(array $datos): array
    {
        $fila = array_replace(array_fill_keys(self::COLUMNAS, ''), array_intersect_key($datos, array_flip(self::COLUMNAS)));
        $fila['id'] = $fila['occurrenceID'];
        // ISO 3166-1: el cambio de caja sólo se aplica al archivo de intercambio.
        $codigo = strtoupper(trim((string) $fila['countryCode']));
        $fila['countryCode'] = preg_match('/^[A-Z]{2}$/D', $codigo) ? $codigo : '';
        if ($fila['countryCode'] === '' && mb_strtolower(trim((string) $fila['country'])) === 'ecuador') $fila['countryCode'] = 'EC';
        $fila['occurrenceStatus'] = EstadoOcurrenciaPublico::deteccion($fila['occurrenceStatus']) ?? '';
        $fila['language'] = strtolower(trim((string) $fila['language']));
        return $fila;
    }

    public static function fecha(?string $inicio, ?string $fin, ?string $verbatim): string
    {
        $inicio = trim($inicio ?? ''); $fin = trim($fin ?? ''); $original = trim($verbatim ?? '');
        // La fecha normalizada histórica puede tener día 01 para una fuente
        // que sólo informaba año o mes. No exportar esa precisión inventada.
        if (preg_match('/^\d{4}(?:-(?:0[1-9]|1[0-2]))?$/D', $original)) return $original;
        if ($inicio === '') return '';
        return $fin !== '' && $fin !== $inicio ? $inicio.'/'.$fin : $inicio;
    }

    public static function fechaIsoValida(string $valor): bool
    {
        $partes = explode('/', $valor);
        if (count($partes) > 2) return false;
        foreach ($partes as $parte) {
            if (!preg_match('/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/D', $parte, $m) || (int)$m[1] < 1) return false;
            if (isset($m[2]) && ((int)$m[2] < 1 || (int)$m[2] > 12)) return false;
            if (isset($m[3]) && !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return false;
        }
        return count($partes) === 1 || strcmp($partes[0], $partes[1]) <= 0;
    }
}
