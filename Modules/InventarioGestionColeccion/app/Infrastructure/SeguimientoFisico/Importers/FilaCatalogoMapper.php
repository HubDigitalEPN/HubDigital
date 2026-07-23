<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ParsearFechaVerbatim;

/**
 * Mapper puro: array asociativo de fila de Excel → FilaMapeada.
 *
 * Aplica las correcciones de typo del Excel original (spec §6):
 *   - invidualCount → individual_count
 *   - basisofRecord → basis_of_record
 *   - dECimalLatitude → decimal_latitude (case-insensitive)
 *   - typECaste → type_caste
 *   - iInfraspecificEpithet → infraspecific_epithet
 *
 * Reglas de "cero pérdida" (spec §0):
 *   - Toda fila genera un FilaMapeada (nunca lanza, sólo acumula warnings).
 *   - Cuando un dato estructurado no se puede parsear (count no numérico,
 *     coords con texto), se mueve al campo `*_verbatim` correspondiente.
 *   - Si ocurrence_id está ausente, se acumula warning para que el orchestrator
 *     marque la fila con estado_revision=pendiente.
 *
 * El mapper genera `codigoCatalogo` (interno) cuando la fila no lo trae —
 * estrategia: occurrenceID > oldCode > prefijo "MEPN:INV:" + random.
 */
final class FilaCatalogoMapper
{
    /** Pivot de siglo per spec del usuario: yy ≤ 25 → 20yy, yy > 25 → 19yy. */
    public const PIVOT_SIGLO_DOS_DIGITOS = 25;

    public function mapear(array $fila): FilaMapeada
    {
        $normalizada = $this->normalizarClaves($fila);
        $warnings = [];

        // Identificadores
        $occurrenceId = $this->limpiar($normalizada['occurrence_id'] ?? null);
        $oldCode = $this->limpiar($normalizada['old_code'] ?? null);
        $catalogNumber = $this->limpiar($normalizada['catalog_number'] ?? null);
        $cardexLiquid = $this->limpiar(
            $normalizada['cardex_liquid_col_code'] ?? $normalizada['cardex_liquid_collection_code'] ?? null
        );

        if ($occurrenceId === null) {
            $warnings[] = 'occurrence_id ausente';
        }

        // codigo_catalogo interno: preferir occurrence_id, luego old_code, luego generar.
        $codigoCatalogo = $occurrenceId
            ?? $oldCode
            ?? $catalogNumber
            ?? ('MEPN:INV:GEN-'.bin2hex(random_bytes(6)));

        // Taxonomía: verbatim explícito del CSV preferido sobre scientific_name.
        $taxonVerbatim = $this->limpiar(
            $normalizada['taxon_verbatim'] ?? $normalizada['scientific_name'] ?? null
        );

        // Localidad: verbatim explícito del CSV preferido. Luego verbatim_locality, luego locality_name.
        $localityName = $this->limpiar($normalizada['locality_name'] ?? null);

        // Fuente de la localidad: puede venir como una sola celda separada por comas
        // en orden geográfico ("País, Provincia, Localidad, …").
        $fuenteLocalidad = $this->limpiar(
            $normalizada['localidad_verbatim']
            ?? $normalizada['verbatim_locality']
            ?? $normalizada['locality_name']
            ?? null
        );
        // Segmentos normalizados (por si viene "País, Provincia, Localidad, …").
        $partes = $this->partesLocalidad($fuenteLocalidad);
        // Verbatim: cadena normalizada, cada segmento con su propia caja. Se
        // preserva entera para trazabilidad y para agrupar en la bandeja de
        // revisión ("YASUNÍ" y "yasuní" caen en el mismo grupo).
        $localidadVerbatim = $partes === [] ? null : implode(', ', $partes);

        // Valores explícitos de sus propias columnas (se dejan crudos: no se
        // normalizan para no dañar códigos como "USA").
        $country = $this->limpiar($normalizada['country'] ?? null);
        $stateProvince = $this->limpiar($normalizada['state_province'] ?? null);
        $municipality = $this->limpiar($normalizada['municipality'] ?? null);

        // Si la localidad viene en una sola celda separada por comas, descompónla
        // (país→provincia→…→localidad) y RELLENA solo los campos que no traigan ya
        // un valor explícito de su propia columna.
        if (count($partes) >= 2) {
            $desc = $this->descomponerLocalidad($partes);
            $country ??= $desc['country'];
            $stateProvince ??= $desc['stateProvince'];
            $municipality ??= $desc['municipality'];
            $localityName ??= $desc['localityName'];
        }

        // La localidad específica es solo la última palabra tras la coma. Cuando
        // localityName viene en su propia columna y es compuesta (ej. "Tena,
        // Reserva Jatun Sacha, sendero X"), nos quedamos con "sendero X". El texto
        // completo se conserva en localidad_verbatim (ubicación anterior).
        $localityName = $this->ultimoSegmentoLocalidad($localityName);

        // Valor de trabajo: la parte más específica disponible (normalizada).
        $localidad = $this->normalizarLocalidad(
            $localityName ?? $municipality ?? $stateProvince ?? $country
        ) ?? '';

        // Fechas — usa parsearRango para soportar "14-26/feb/2001", "Jun-Jul-1998", etc.
        // Prefiere fecha_verbatim explícito del CSV sobre event_date.
        $fechaVerbatim = $this->limpiar(
            $normalizada['fecha_verbatim'] ?? $normalizada['event_date'] ?? null
        );
        $fechaColectaFinVerbatim = $this->limpiar($normalizada['datecollected_end'] ?? null);
        $parsedInicio = null;
        $parsedFin = null;
        if ($fechaVerbatim !== null) {
            [$parsedInicio, $parsedFin] = ParsearFechaVerbatim::parsearRango(
                $fechaVerbatim,
                self::PIVOT_SIGLO_DOS_DIGITOS,
            );
            if ($parsedInicio === null) {
                $warnings[] = "fecha_colecta no parseable ('{$fechaVerbatim}')";
            }
        }
        // datecollectedEnd explícito tiene prioridad sobre el fin del rango parseado.
        if ($fechaColectaFinVerbatim !== null) {
            $parsedFinExplicito = ParsearFechaVerbatim::parsear(
                $fechaColectaFinVerbatim,
                self::PIVOT_SIGLO_DOS_DIGITOS,
            );
            if ($parsedFinExplicito !== null) {
                $parsedFin = $parsedFinExplicito;
            }
        }
        $fechaColecta = $parsedInicio?->format('Y-m-d') ?? '';

        // Colector
        $colector = $this->limpiar($normalizada['recorded_by'] ?? null) ?? '';

        // individual_count: si no es numérico, mover a verbatim.
        // Prefiere individual_count_verbatim explícito del CSV.
        $individualCountVerbatim = $this->limpiar($normalizada['individual_count_verbatim'] ?? null);
        $individualCountRaw = $this->limpiar($normalizada['individual_count'] ?? $normalizada['invidual_count'] ?? null);
        $individualCount = null;
        if ($individualCountRaw !== null) {
            if (ctype_digit(ltrim($individualCountRaw, '-'))) {
                $individualCount = (int) $individualCountRaw;
            } else {
                $individualCountVerbatim ??= $individualCountRaw;
                $warnings[] = "individual_count no numérico ('{$individualCountRaw}')";
            }
        }

        // Coordenadas: si decimal_latitude/longitude no son numéricas, mover a coord_verbatim.
        // Prefiere coord_verbatim explícito del CSV.
        $coordVerbatimExplicito = $this->limpiar($normalizada['coord_verbatim'] ?? null);
        $latRaw = $this->limpiar($normalizada['decimal_latitude'] ?? null);
        $lonRaw = $this->limpiar($normalizada['decimal_longitude'] ?? null);
        $decimalLatitude = null;
        $decimalLongitude = null;
        $coordVerbatim = $coordVerbatimExplicito;
        if ($latRaw !== null || $lonRaw !== null) {
            $latNum = $latRaw !== null && is_numeric($latRaw) ? (float) $latRaw : null;
            $lonNum = $lonRaw !== null && is_numeric($lonRaw) ? (float) $lonRaw : null;
            if ($latNum !== null && $lonNum !== null
                && $latNum >= -90.0 && $latNum <= 90.0
                && $lonNum >= -180.0 && $lonNum <= 180.0) {
                $decimalLatitude = $latNum;
                $decimalLongitude = $lonNum;
            } else {
                $coordVerbatim ??= trim(($latRaw ?? '').' / '.($lonRaw ?? ''), ' /');
                $warnings[] = "coordenadas no numéricas o fuera de rango ('{$coordVerbatim}')";
            }
        }

        // Elevación min/max
        $elevationMinM = $this->floatOnumerico($normalizada['minimum_elevation_in_meters'] ?? null);
        $elevationMaxM = $this->floatOnumerico($normalizada['maximum_elevation_in_meters'] ?? null);

        // Endemic: boolean inferido de "Yes"/"No"/"Sí"/"1"/"0". La plantilla v2 lo
        // trata como TEXTO descriptivo ("yes, Colombia", "endemic, Galápagos"); ese
        // verbatim se preserva aparte y el booleano queda como mejor-esfuerzo.
        $endemic = $this->parsearBoolean($normalizada['endemic'] ?? null);
        $endemicVerbatim = $this->limpiar($normalizada['endemic'] ?? null);

        // Campos plantilla v2 (aditivos): se leen crudos y se preservan tal cual.
        $recordNumber = $this->limpiar($normalizada['record_number'] ?? null);
        $origin = $this->limpiar($normalizada['origin'] ?? null);
        $identifiedBy = $this->limpiar($normalizada['identified_by'] ?? null);
        $dateDetermined = $this->limpiar($normalizada['date_determined'] ?? null);
        $researchPermit = $this->limpiar($normalizada['research_permit'] ?? null);
        $transportPermit = $this->limpiar($normalizada['transport_permit'] ?? null);
        $exportImportAuthorization = $this->limpiar($normalizada['export_import_authorization'] ?? null);
        $scientificNameAuthorship = $this->limpiar($normalizada['scientific_name_authorship'] ?? null);
        $latLonMaxError = $this->limpiar($normalizada['lat_lon_max_error'] ?? null);
        $clade = $this->limpiar($normalizada['clade'] ?? null);
        $identificationQualifier = $this->limpiar($normalizada['identification_qualifier'] ?? null);
        $identificationRemarks = $this->limpiar($normalizada['identification_remarks'] ?? null);
        $vernacularName = $this->limpiar($normalizada['vernacular_name'] ?? null);
        $typeNotes = $this->limpiar($normalizada['type_notes'] ?? null);
        $continent = $this->limpiar($normalizada['continent'] ?? null);
        $countryCode = $this->limpiar($normalizada['country_code'] ?? null);
        $localityNotes = $this->limpiar($normalizada['locality_notes'] ?? null);
        $localityCode = $this->limpiar($normalizada['locality_code'] ?? null);
        $elevationMaxError = $this->limpiar($normalizada['elevation_max_error'] ?? null);
        $verbatimElevation = $this->limpiar($normalizada['verbatim_elevation'] ?? null);
        $verbatimDepth = $this->limpiar($normalizada['verbatim_depth'] ?? null);
        $verbatimLatitude = $this->limpiar($normalizada['verbatim_latitude'] ?? null);
        $verbatimLongitude = $this->limpiar($normalizada['verbatim_longitude'] ?? null);
        $verbatimCoordinateSystem = $this->limpiar($normalizada['verbatim_coordinate_system'] ?? null);
        $verbatimSrs = $this->limpiar($normalizada['verbatim_srs'] ?? null);
        $informationWithheld = $this->limpiar($normalizada['information_withheld'] ?? null);
        $priorOwner = $this->limpiar($normalizada['prior_owner'] ?? null);
        $locatedAt = $this->limpiar($normalizada['located_at'] ?? null);
        $iptUpload = $this->limpiar($normalizada['ipt_upload'] ?? null);
        $recordCreatedBy = $this->limpiar(
            $normalizada['record_created_by,month-year'] ?? $normalizada['record_created_by'] ?? null
        );
        $responsibleResearcherExport = $this->limpiar(
            $normalizada['responsible_researcher_or_applicant_for_export'] ?? null
        );

        return new FilaMapeada(
            codigoCatalogo: $codigoCatalogo,
            occurrenceId: $occurrenceId,
            catalogNumber: $catalogNumber,
            oldCode: $oldCode,
            cardexLiquidCollectionCode: $cardexLiquid,
            taxonVerbatim: $taxonVerbatim,
            localidadVerbatim: $localidadVerbatim,
            localidad: $localidad,
            fechaVerbatim: $fechaVerbatim,
            fechaColecta: $fechaColecta,
            fechaColectaFin: $parsedFin?->format('Y-m-d'),
            colector: $colector,
            individualCount: $individualCount,
            individualCountVerbatim: $individualCountVerbatim,
            sex: $this->limpiar($normalizada['sex'] ?? null),
            lifeStage: $this->limpiar($normalizada['life_stage'] ?? null),
            caste: $this->limpiar($normalizada['caste'] ?? $normalizada['typecaste'] ?? null),
            typeStatus: $this->limpiar($normalizada['type_status'] ?? null),
            preparations: $this->limpiar($normalizada['preparations'] ?? null),
            disposition: $this->limpiar($normalizada['disposition'] ?? null),
            occurrenceStatus: $this->limpiar($normalizada['occurrence_status'] ?? null),
            specimenNotes: $this->limpiar($normalizada['specimen_notes'] ?? $normalizada['comment'] ?? null),
            country: $country,
            stateProvince: $stateProvince,
            municipality: $municipality,
            localityName: $localityName,
            decimalLatitude: $decimalLatitude,
            decimalLongitude: $decimalLongitude,
            coordVerbatim: $coordVerbatim,
            geodeticDatum: $this->limpiar($normalizada['geodetic_datum'] ?? null),
            elevationMinM: $elevationMinM,
            elevationMaxM: $elevationMaxM,
            biome: $this->limpiar($normalizada['biome'] ?? null),
            habitat: $this->limpiar($normalizada['habitat'] ?? null),
            microhabitat: $this->limpiar($normalizada['microhabitat'] ?? null),
            biogeographicRegion: $this->limpiar($normalizada['biogeographic_region'] ?? null),
            endemic: $endemic,
            dnaNotes: $this->limpiar($normalizada['dna_notes'] ?? null),
            occurrenceRemarks: $this->limpiar($normalizada['occurrence_remarks'] ?? null),
            taxonomicNotes: $this->limpiar($normalizada['taxonomic_notes'] ?? null),
            actaRecepcion: $this->limpiar($normalizada['acta_recepcion'] ?? $normalizada['#acta_recepcion'] ?? null),
            warnings: $warnings,
            recordNumber: $recordNumber,
            origin: $origin,
            identifiedBy: $identifiedBy,
            dateDetermined: $dateDetermined,
            researchPermit: $researchPermit,
            transportPermit: $transportPermit,
            exportImportAuthorization: $exportImportAuthorization,
            scientificNameAuthorship: $scientificNameAuthorship,
            latLonMaxError: $latLonMaxError,
            clade: $clade,
            identificationQualifier: $identificationQualifier,
            identificationRemarks: $identificationRemarks,
            vernacularName: $vernacularName,
            typeNotes: $typeNotes,
            continent: $continent,
            countryCode: $countryCode,
            localityNotes: $localityNotes,
            localityCode: $localityCode,
            elevationMaxError: $elevationMaxError,
            verbatimElevation: $verbatimElevation,
            verbatimDepth: $verbatimDepth,
            verbatimLatitude: $verbatimLatitude,
            verbatimLongitude: $verbatimLongitude,
            verbatimCoordinateSystem: $verbatimCoordinateSystem,
            verbatimSrs: $verbatimSrs,
            informationWithheld: $informationWithheld,
            priorOwner: $priorOwner,
            locatedAt: $locatedAt,
            iptUpload: $iptUpload,
            recordCreatedBy: $recordCreatedBy,
            responsibleResearcherExport: $responsibleResearcherExport,
            endemicVerbatim: $endemicVerbatim,
        );
    }

    /**
     * Normaliza claves del array: camelCase / snake_case mezclado, espacios,
     * typos del Excel → snake_case canónico. Público para que el orchestrator
     * pase el array normalizado a ConstructorTaxonomiaImport sin re-snakeizar.
     *
     * @param  array<string, mixed>  $fila
     * @return array<string, string|null>
     */
    public function normalizarClaves(array $fila): array
    {
        $out = [];
        foreach ($fila as $clave => $valor) {
            $key = $this->snakeize((string) $clave);
            // Aplicar typos conocidos del Excel.
            $key = match ($key) {
                'invidual_count' => 'individual_count',
                'basisof_record' => 'basis_of_record',
                'd_ecimal_latitude' => 'decimal_latitude',
                'd_ecimal_longitude' => 'decimal_longitude',
                'type_ecaste', 'typ_ecaste' => 'caste',
                'i_infraspecific_epithet' => 'infraspecific_epithet',
                default => $key,
            };
            $out[$key] = is_string($valor) ? $valor : ($valor === null ? null : (string) $valor);
        }

        return $out;
    }

    private function snakeize(string $clave): string
    {
        $clave = trim($clave);
        // Remover marcadores como '#' o '/'.
        $clave = str_replace(['#', '/'], ['', '_'], $clave);
        // Espacios → underscore.
        $clave = preg_replace('/\s+/', '_', $clave) ?? $clave;
        // camelCase → snake_case.
        $clave = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $clave) ?? $clave;

        return mb_strtolower($clave);
    }

    private function limpiar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $trim = trim($valor);

        return $trim === '' ? null : $trim;
    }

    /**
     * Normaliza la caja de una localidad: primera letra en mayúscula y el resto
     * en minúsculas (multibyte, respeta acentos). Ej: "YASUNÍ" -> "Yasuní".
     * Devuelve null si el valor está vacío.
     */
    private function normalizarLocalidad(?string $valor): ?string
    {
        $valor = $this->limpiar($valor);
        if ($valor === null) {
            return null;
        }

        $minuscula = mb_strtolower($valor, 'UTF-8');

        return mb_strtoupper(mb_substr($minuscula, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($minuscula, 1, null, 'UTF-8');
    }

    /**
     * Devuelve solo el último segmento tras la coma, normalizado. Es la
     * "ubicación específica" según la regla del usuario. "Tena, Reserva Jatun
     * Sacha, sendero X" -> "Sendero X". Sin comas, normaliza el valor entero.
     * null si está vacío.
     */
    private function ultimoSegmentoLocalidad(?string $valor): ?string
    {
        $valor = $this->limpiar($valor);
        if ($valor === null) {
            return null;
        }
        if (str_contains($valor, ',')) {
            $partes = array_values(array_filter(array_map('trim', explode(',', $valor)), fn ($p) => $p !== ''));
            $valor = $partes === [] ? $valor : end($partes);
        }

        return $this->normalizarLocalidad($valor);
    }

    /**
     * Parte una localidad separada por comas en segmentos no vacíos, cada uno
     * con la caja normalizada. "ECUADOR, ORELLANA, YASUNÍ" -> ["Ecuador","Orellana","Yasuní"].
     *
     * @return string[]
     */
    private function partesLocalidad(?string $valor): array
    {
        if ($valor === null || ! str_contains($valor, ',')) {
            return $valor === null ? [] : [$this->normalizarLocalidad($valor)];
        }

        return array_values(array_filter(array_map(
            fn (string $p): ?string => $this->normalizarLocalidad($p),
            explode(',', $valor),
        )));
    }

    /**
     * Descompone una localidad en orden geográfico (país primero) en los campos
     * Darwin Core. El último segmento es siempre la localidad más específica;
     * los previos rellenan country/stateProvince/municipality.
     *
     * @param  string[]  $partes  segmentos ya normalizados (2 o más)
     * @return array{country: ?string, stateProvince: ?string, municipality: ?string, localityName: ?string}
     */
    private function descomponerLocalidad(array $partes): array
    {
        $out = ['country' => null, 'stateProvince' => null, 'municipality' => null, 'localityName' => null];
        $n = count($partes);
        if ($n === 0) {
            return $out;
        }

        // La localidad específica es SIEMPRE el último segmento tras la coma
        // (decisión del usuario). Los segmentos previos alimentan país/provincia/
        // cantón; el string completo se preserva aparte en localidad_verbatim.
        $out['country'] = $partes[0];
        $out['localityName'] = $partes[$n - 1];
        if ($n >= 3) {
            $out['stateProvince'] = $partes[1];
        }
        if ($n >= 4) {
            $out['municipality'] = $partes[2];
        }

        return $out;
    }

    private function floatOnumerico(?string $valor): ?float
    {
        $limpio = $this->limpiar($valor);
        if ($limpio === null) {
            return null;
        }

        return is_numeric($limpio) ? (float) $limpio : null;
    }

    private function parsearBoolean(?string $valor): ?bool
    {
        $limpio = $this->limpiar($valor);
        if ($limpio === null) {
            return null;
        }

        return match (mb_strtolower($limpio)) {
            '1', 'true', 'yes', 'si', 'sí', 'verdadero', 'endemic', 'endémico', 'endemico' => true,
            '0', 'false', 'no', 'falso', 'no_endemic' => false,
            default => null,
        };
    }
}
