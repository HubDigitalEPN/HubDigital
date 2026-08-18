<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\FiltrosPendientes;
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
    /**
     * Regímenes de tenencia que sacan al espécimen de la vitrina pública.
     *
     * `Devuelto`: volvió a manos de su depositante y ya no está en el museo.
     * `Cuarentena`: aislado hasta su revisión sanitaria, aún no forma parte de lo que la
     * colección exhibe.
     *
     * Hoy este filtro no cambia ningún resultado, porque el material de depósito entra
     * sin `occurrence_id` y sin `taxon_id` y ya queda fuera por eso. Se pone ahora
     * porque el día que se le asigne un `occurrence_id` para publicarlo en GBIF, esa
     * puerta se abriría sola y de par en par.
     *
     * @var string[]
     */
    private const CUSTODIAS_NO_PUBLICABLES = ['Devuelto', 'Cuarentena'];

    /**
     * Predicado reutilizable para las consultas construidas con el query builder.
     *
     * El `OR IS NULL` es obligatorio: `NOT IN` descarta las filas nulas, y los ~48k
     * especímenes heredados del catálogo no tienen régimen declarado.
     */
    private function soloMaterialEnColeccion(): \Closure
    {
        return function ($q): void {
            $q->whereNull('e.estado_custodia')
                ->orWhereNotIn('e.estado_custodia', self::CUSTODIAS_NO_PUBLICABLES);
        };
    }

    public function buscarPorOccurrenceId(string $occurrenceId): ?DatosEspecimenProveedor
    {
        $fila = DB::selectOne('
            WITH RECURSIVE ancestors AS (
                SELECT t.id, t.nombre_cientifico, t.rango, t.padre_id
                FROM taxonomia.taxones t
                INNER JOIN taxonomia.especimenes e ON e.taxon_id = t.id
                WHERE e.occurrence_id = ?
                  AND (e.estado_custodia IS NULL OR e.estado_custodia NOT IN (\'Devuelto\', \'Cuarentena\'))
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
                  AND (e.estado_custodia IS NULL OR e.estado_custodia NOT IN (\'Devuelto\', \'Cuarentena\'))
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
                    SELECT DISTINCT taxon_id FROM taxonomia.especimenes
                    WHERE occurrence_id IS NOT NULL
                      AND (estado_custodia IS NULL OR estado_custodia NOT IN (\'Devuelto\', \'Cuarentena\'))
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
            AND (e.estado_custodia IS NULL OR e.estado_custodia NOT IN (\'Devuelto\', \'Cuarentena\'))
            AND t.rango = \'especie\'
        ');

        return array_map(fn ($fila) => $this->traducir($fila), $filas);
    }

    public function contar(array $especimenIdsExcluir = [], ?FiltrosPendientes $filtros = null): int
    {
        $q = DB::table('taxonomia.especimenes as e')
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->whereNotNull('e.occurrence_id')
            ->where($this->soloMaterialEnColeccion())
            ->where('t.rango', 'especie');

        if ($especimenIdsExcluir !== []) {
            $q->whereNotIn('e.id', $especimenIdsExcluir);
        }

        $this->aplicarFiltros($q, $filtros);

        return $q->count();
    }

    /** @return DatosEspecimenProveedor[] */
    public function obtenerPaginado(int $offset, int $limit, array $especimenIdsExcluir = [], ?FiltrosPendientes $filtros = null): array
    {
        $q = DB::table('taxonomia.especimenes as e')
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->leftJoin('taxonomia.muestras_colecta as mc', 'mc.id', '=', 'e.muestra_id')
            ->whereNotNull('e.occurrence_id')
            ->where($this->soloMaterialEnColeccion())
            ->where('t.rango', 'especie');

        if ($especimenIdsExcluir !== []) {
            $q->whereNotIn('e.id', $especimenIdsExcluir);
        }

        $this->aplicarFiltros($q, $filtros);

        $filas = $q->orderBy('e.occurrence_id')
            ->offset(max(0, $offset))
            ->limit($limit)
            ->get([
                'e.id',
                'e.taxon_id',
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
            ]);

        if ($filas->isEmpty()) {
            return [];
        }

        $taxonIds = $filas->pluck('taxon_id')->unique()->values()->all();
        $familiaGeneroPorTaxon = $this->resolverFamiliaGenero($taxonIds);

        return $filas->map(function ($fila) use ($familiaGeneroPorTaxon) {
            $resumen = $familiaGeneroPorTaxon[$fila->taxon_id] ?? null;
            $fila->family = $resumen->family ?? null;
            $fila->genus = $resumen->genus ?? null;

            return $this->traducir($fila);
        })->all();
    }

    public function obtenerColectoresPendientes(array $especimenIdsExcluir = []): array
    {
        $q = DB::table('taxonomia.especimenes as e')
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->whereNotNull('e.occurrence_id')
            ->where($this->soloMaterialEnColeccion())
            ->whereNotNull('e.colector')
            ->where('e.colector', '!=', '')
            ->where('t.rango', 'especie');

        if ($especimenIdsExcluir !== []) {
            $q->whereNotIn('e.id', $especimenIdsExcluir);
        }

        return $q->distinct()
            ->orderBy('e.colector')
            ->pluck('e.colector')
            ->all();
    }

    private function aplicarFiltros(Builder $q, ?FiltrosPendientes $filtros): void
    {
        if ($filtros === null) {
            return;
        }

        if ($filtros->busquedaCatalogo !== null) {
            $q->where('e.occurrence_id', 'ILIKE', '%'.$filtros->busquedaCatalogo.'%');
        }

        if ($filtros->busquedaTaxonomia !== null) {
            $q->where('t.nombre_cientifico', 'ILIKE', '%'.$filtros->busquedaTaxonomia.'%');
        }

        if ($filtros->fechaDesde !== null) {
            $q->where('e.fecha_colecta', '>=', $filtros->fechaDesde);
        }

        if ($filtros->fechaHasta !== null) {
            $q->where('e.fecha_colecta', '<=', $filtros->fechaHasta);
        }

        if ($filtros->colector !== null) {
            $q->where('e.colector', $filtros->colector);
        }
    }

    /**
     * Resuelve familia y género para un conjunto de taxon_ids mediante CTE recursivo.
     *
     * @param  list<int|string>  $taxonIds
     * @return array<int|string, object> taxon_id => { family, genus }
     */
    private function resolverFamiliaGenero(array $taxonIds): array
    {
        if ($taxonIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taxonIds), '?'));

        $filas = DB::select("
            WITH RECURSIVE ancestors AS (
                SELECT t.id, t.nombre_cientifico, t.rango, t.padre_id, t.id AS origin_id
                FROM taxonomia.taxones t
                WHERE t.id IN ($placeholders)
                UNION ALL
                SELECT t.id, t.nombre_cientifico, t.rango, t.padre_id, a.origin_id
                FROM taxonomia.taxones t
                INNER JOIN ancestors a ON t.id = a.padre_id
            )
            SELECT
                origin_id,
                MAX(CASE WHEN rango = 'familia' THEN nombre_cientifico END) AS family,
                MAX(CASE WHEN rango = 'genero'  THEN nombre_cientifico END) AS genus
            FROM ancestors
            GROUP BY origin_id
        ", $taxonIds);

        $indexado = [];
        foreach ($filas as $fila) {
            $indexado[$fila->origin_id] = $fila;
        }

        return $indexado;
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

        // Filtrar por rango='especie' evita colisión cuando el mismo occurrence_id tiene
        // múltiples filas en taxonomia.especimenes con distinto taxon_id (rango).
        // Sin este filtro, el indexado $porOccurrenceId sobrescribe la fila-especie con
        // otras de rangos superiores, y el divulgable termina apuntando a un UUID cuyo
        // taxon no es especie → invisible en la tabla de divulgados.
        $filas = DB::table('taxonomia.especimenes as e')
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->leftJoin('taxonomia.muestras_colecta as mc', 'mc.id', '=', 'e.muestra_id')
            ->whereIn('e.occurrence_id', $occurrenceIds)
            ->where('t.rango', 'especie')
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
        //
        // Comparación case-insensitive y con espacios normalizados: el chatbot puede recibir
        // el nombre científico del LLM con casing arbitrario o con doble espacio.
        $normalizado = preg_replace('/\s+/', ' ', trim($scientificName)) ?? $scientificName;

        $filas = DB::table('taxonomia.especimenes as e')
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->join('taxonomia.taxones as tx_genus', 'tx_genus.id', '=', 't.padre_id')
            ->leftJoin('taxonomia.muestras_colecta as mc', 'mc.id', '=', 'e.muestra_id')
            ->whereNotNull('e.occurrence_id')
            ->where($this->soloMaterialEnColeccion())
            ->whereRaw(
                "LOWER(CASE WHEN t.nombre_cientifico LIKE (tx_genus.nombre_cientifico || ' %')
                            THEN t.nombre_cientifico
                            ELSE tx_genus.nombre_cientifico || ' ' || t.nombre_cientifico
                       END) = LOWER(?)",
                [$normalizado]
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
