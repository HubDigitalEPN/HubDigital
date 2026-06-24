<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;

/**
 * ACL — Anti-Corruption Layer entre CatalogoPublico (Customer) e InventarioGestionColeccion (Supplier).
 * Toda la traducción del lenguaje del Supplier al lenguaje de CatalogoPublico vive aquí.
 * Si taxonomia.especimenes cambia su schema, solo este archivo se modifica.
 *
 * Familia y género se resuelven subiendo la jerarquía taxonómica via CTE recursivo integrado en
 * la misma consulta: una sola query por operación, sin N+1.
 */
final class InventarioGestionColeccionEspecimenAdapter implements ProveedorEspecimenesPort
{
    public function buscarPorOccurrenceId(string $occurrenceId): ?DatosEspecimenProveedor
    {
        $fila = DB::selectOne('
            WITH RECURSIVE ancestors AS (
                SELECT t.id, t.nombre_cientifico, t.rango, t.padre_id
                FROM taxonomia.taxones t
                INNER JOIN taxonomia.especimenes e ON e.taxon_id = t.id
                WHERE e.occurrence_id = ?
                UNION ALL
                SELECT t.id, t.nombre_cientifico, t.rango, t.padre_id
                FROM taxonomia.taxones t
                INNER JOIN ancestors a ON t.id = a.padre_id
            ),
            taxon_resumen AS (
                SELECT
                    MAX(CASE WHEN rango = \'familia\' THEN nombre_cientifico END) AS family,
                    MAX(CASE WHEN rango = \'genero\'  THEN nombre_cientifico END) AS genus
                FROM ancestors
            )
            SELECT
                e.id,
                e.occurrence_id,
                e.colector,
                e.individual_count,
                e.disposition,
                e.occurrence_status,
                e.specimen_notes,
                e.country,
                e.state_province,
                e.locality_name,
                e.decimal_latitude,
                e.decimal_longitude,
                e.fecha_colecta,
                e.caste,
                e.life_stage,
                e.elevation_min_m,
                e.elevation_max_m,
                mc.sampling_protocol,
                t.nombre_cientifico,
                tr.family,
                tr.genus
            FROM taxonomia.especimenes e
            INNER JOIN taxonomia.taxones t ON t.id = e.taxon_id
            CROSS JOIN taxon_resumen tr
            LEFT JOIN taxonomia.muestras_colecta mc ON mc.id = e.muestra_id
            WHERE e.occurrence_id = ?
        ', [$occurrenceId, $occurrenceId]);

        if ($fila === null) {
            return null;
        }

        return $this->traducir($fila);
    }

    /** @return DatosEspecimenProveedor[] */
    public function obtenerTodos(): array
    {
        $filas = DB::select('
            WITH RECURSIVE ancestors AS (
                SELECT t.id, t.nombre_cientifico, t.rango, t.padre_id, t.id AS origin_id
                FROM taxonomia.taxones t
                WHERE t.id IN (
                    SELECT DISTINCT taxon_id FROM taxonomia.especimenes WHERE occurrence_id IS NOT NULL
                )
                AND t.rango = \'especie\'
                UNION ALL
                SELECT t.id, t.nombre_cientifico, t.rango, t.padre_id, a.origin_id
                FROM taxonomia.taxones t
                INNER JOIN ancestors a ON t.id = a.padre_id
            ),
            taxon_resumen AS (
                SELECT
                    origin_id,
                    MAX(CASE WHEN rango = \'familia\' THEN nombre_cientifico END) AS family,
                    MAX(CASE WHEN rango = \'genero\'  THEN nombre_cientifico END) AS genus
                FROM ancestors
                GROUP BY origin_id
            )
            SELECT
                e.id,
                e.occurrence_id,
                e.colector,
                e.individual_count,
                e.disposition,
                e.occurrence_status,
                e.specimen_notes,
                e.country,
                e.state_province,
                e.locality_name,
                e.decimal_latitude,
                e.decimal_longitude,
                e.fecha_colecta,
                e.caste,
                e.life_stage,
                e.elevation_min_m,
                e.elevation_max_m,
                mc.sampling_protocol,
                t.nombre_cientifico,
                tr.family,
                tr.genus
            FROM taxonomia.especimenes e
            INNER JOIN taxonomia.taxones t ON t.id = e.taxon_id
            LEFT JOIN taxon_resumen tr ON tr.origin_id = e.taxon_id
            LEFT JOIN taxonomia.muestras_colecta mc ON mc.id = e.muestra_id
            WHERE e.occurrence_id IS NOT NULL
            AND t.rango = \'especie\'
        ');

        return array_map(fn ($fila) => $this->traducir($fila), $filas);
    }

    /**
     * @param  list<string>  $occurrenceIds
     * @return DatosEspecimenProveedor[]
     */
    public function buscarPorOccurrenceIds(array $occurrenceIds): array
    {
        if ($occurrenceIds === []) {
            return [];
        }

        $filas = DB::table('taxonomia.especimenes as e')
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->leftJoin('taxonomia.muestras_colecta as mc', 'mc.id', '=', 'e.muestra_id')
            ->whereIn('e.occurrence_id', $occurrenceIds)
            ->select([
                'e.id',
                'e.occurrence_id',
                'e.colector',
                'e.individual_count',
                'e.disposition',
                'e.occurrence_status',
                'e.specimen_notes',
                'e.country',
                'e.state_province',
                'e.locality_name',
                'e.decimal_latitude',
                'e.decimal_longitude',
                'e.fecha_colecta',
                'e.caste',
                'e.life_stage',
                'e.elevation_min_m',
                'e.elevation_max_m',
                'mc.sampling_protocol',
                't.nombre_cientifico',
                DB::raw('NULL as family'),  // jerarquía completa no requerida en detalle de especie
                DB::raw('NULL as genus'),
            ])
            ->orderByRaw('e.disposition IS NULL')
            ->orderBy('e.occurrence_id')
            ->get();

        return $filas->map(fn ($fila) => $this->traducir($fila))->all();
    }

    /** @return DatosEspecimenProveedor[] */
    public function buscarPorNombreCientifico(string $scientificName): array
    {
        // El árbol construye el nombre con: si taxon ya empieza con el género → usarlo tal cual,
        // si no → genus + ' ' + taxon. Replicamos esa misma lógica en SQL para que el nombre
        // pasado desde $this->taxon (Livewire) coincida con lo que devuelve el arbol builder.
        $filas = DB::table('taxonomia.especimenes as e')
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->join('taxonomia.taxones as tx_genus', 'tx_genus.id', '=', 't.padre_id')
            ->leftJoin('taxonomia.muestras_colecta as mc', 'mc.id', '=', 'e.muestra_id')
            ->whereNotNull('e.occurrence_id')
            ->whereRaw(
                "CASE WHEN t.nombre_cientifico LIKE (tx_genus.nombre_cientifico || ' %')
                      THEN t.nombre_cientifico
                      ELSE tx_genus.nombre_cientifico || ' ' || t.nombre_cientifico
                 END = ?",
                [$scientificName]
            )
            ->select([
                'e.id',
                'e.occurrence_id',
                'e.colector',
                'e.individual_count',
                'e.disposition',
                'e.occurrence_status',
                'e.specimen_notes',
                'e.country',
                'e.state_province',
                'e.locality_name',
                'e.decimal_latitude',
                'e.decimal_longitude',
                'e.fecha_colecta',
                'e.caste',
                'e.life_stage',
                'e.elevation_min_m',
                'e.elevation_max_m',
                'mc.sampling_protocol',
                't.nombre_cientifico',
                DB::raw('NULL as family'),
                DB::raw('NULL as genus'),
            ])
            ->orderBy('e.occurrence_id')
            ->get();

        return $filas->map(fn ($fila) => $this->traducir($fila))->all();
    }

    private function traducir(mixed $fila): DatosEspecimenProveedor
    {
        return new DatosEspecimenProveedor(
            especimenId: $fila->id,
            occurrenceId: $fila->occurrence_id,
            scientificName: $fila->nombre_cientifico,
            individualCount: (int) ($fila->individual_count ?? 0),
            typeStatus: $fila->disposition,      // ACL: disposition del Supplier → typeStatus del Customer
            typeNotes: null,                      // sin campo equivalente en el Supplier
            specimenNotes: $fila->specimen_notes,
            samplingProtocol: $fila->sampling_protocol ?? null, // ACL: muestras_colecta.sampling_protocol
            recordedBy: $fila->colector,          // ACL: colector del Supplier → recordedBy del Customer
            occurrenceStatus: $fila->occurrence_status ?? 'present',
            family: $fila->family,
            genus: $fila->genus,
            country: $fila->country,
            localityName: $fila->locality_name,
            decimalLatitude: $fila->decimal_latitude !== null ? (float) $fila->decimal_latitude : null,
            decimalLongitude: $fila->decimal_longitude !== null ? (float) $fila->decimal_longitude : null,
            stateProvince: $fila->state_province,
            eventDate: $fila->fecha_colecta,      // ACL: fecha_colecta del Supplier → eventDate del Customer
            caste: $fila->caste,
            lifeStage: $fila->life_stage,
            elevationMinM: $fila->elevation_min_m !== null ? (float) $fila->elevation_min_m : null,
            elevationMaxM: $fila->elevation_max_m !== null ? (float) $fila->elevation_max_m : null,
        );
    }
}
