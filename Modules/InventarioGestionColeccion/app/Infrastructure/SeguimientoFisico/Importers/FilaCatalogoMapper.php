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

        // Taxonomía: el verbatim siempre se guarda. La resolución a taxon_id es P5.4.
        $taxonVerbatim = $this->limpiar($normalizada['scientific_name'] ?? null);

        // Localidad: localityName preferido; verbatim guarda el original (también localityName).
        $localityName = $this->limpiar($normalizada['locality_name'] ?? null);
        $verbatimLocality = $this->limpiar($normalizada['verbatim_locality'] ?? null);
        $localidadVerbatim = $verbatimLocality ?? $localityName;
        $country = $this->limpiar($normalizada['country'] ?? null);
        $stateProvince = $this->limpiar($normalizada['state_province'] ?? null);
        $municipality = $this->limpiar($normalizada['municipality'] ?? null);
        $localidad = $localityName ?? $country ?? '';

        // Fechas
        $fechaVerbatim = $this->limpiar($normalizada['event_date'] ?? null);
        $fechaColectaFinVerbatim = $this->limpiar($normalizada['datecollected_end'] ?? null);
        $parsedInicio = $fechaVerbatim !== null ? ParsearFechaVerbatim::parsear($fechaVerbatim) : null;
        $parsedFin = $fechaColectaFinVerbatim !== null ? ParsearFechaVerbatim::parsear($fechaColectaFinVerbatim) : null;
        $fechaColecta = $parsedInicio?->format('Y-m-d') ?? '';
        if ($fechaVerbatim !== null && $parsedInicio === null) {
            $warnings[] = "fecha_colecta no parseable ('{$fechaVerbatim}')";
        }

        // Colector
        $colector = $this->limpiar($normalizada['recorded_by'] ?? null) ?? '';

        // individual_count: si no es numérico, mover a verbatim.
        $individualCountRaw = $this->limpiar($normalizada['individual_count'] ?? $normalizada['invidual_count'] ?? null);
        $individualCount = null;
        $individualCountVerbatim = null;
        if ($individualCountRaw !== null) {
            if (ctype_digit(ltrim($individualCountRaw, '-'))) {
                $individualCount = (int) $individualCountRaw;
            } else {
                $individualCountVerbatim = $individualCountRaw;
                $warnings[] = "individual_count no numérico ('{$individualCountRaw}')";
            }
        }

        // Coordenadas: si decimal_latitude/longitude no son numéricas, mover a coord_verbatim.
        $latRaw = $this->limpiar($normalizada['decimal_latitude'] ?? null);
        $lonRaw = $this->limpiar($normalizada['decimal_longitude'] ?? null);
        $decimalLatitude = null;
        $decimalLongitude = null;
        $coordVerbatim = null;
        if ($latRaw !== null || $lonRaw !== null) {
            $latNum = $latRaw !== null && is_numeric($latRaw) ? (float) $latRaw : null;
            $lonNum = $lonRaw !== null && is_numeric($lonRaw) ? (float) $lonRaw : null;
            if ($latNum !== null && $lonNum !== null
                && $latNum >= -90.0 && $latNum <= 90.0
                && $lonNum >= -180.0 && $lonNum <= 180.0) {
                $decimalLatitude = $latNum;
                $decimalLongitude = $lonNum;
            } else {
                $coordVerbatim = trim(($latRaw ?? '').' / '.($lonRaw ?? ''), ' /');
                $warnings[] = "coordenadas no numéricas o fuera de rango ('{$coordVerbatim}')";
            }
        }

        // Elevación min/max
        $elevationMinM = $this->floatOnumerico($normalizada['minimum_elevation_in_meters'] ?? null);
        $elevationMaxM = $this->floatOnumerico($normalizada['maximum_elevation_in_meters'] ?? null);

        // Endemic: boolean inferido de "Yes"/"No"/"Sí"/"1"/"0".
        $endemic = $this->parsearBoolean($normalizada['endemic'] ?? null);

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
        );
    }

    /**
     * Normaliza claves del array: camelCase / snake_case mezclado, espacios,
     * typos del Excel → snake_case canónico.
     *
     * @param  array<string, mixed>  $fila
     * @return array<string, string|null>
     */
    private function normalizarClaves(array $fila): array
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
