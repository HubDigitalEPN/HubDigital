<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenParaArbol;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

final class EloquentProveedorEspecimenesParaArbol implements ProveedorEspecimenesParaArbolPort
{
    /** Filas filtradas y en orden estable para una descarga CSV de memoria acotada. */
    public function cursorParaCsv(FiltrosBusqueda $filtros): LazyCollection
    {
        $query = DB::table('taxonomia.especimenes as te')
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'te.id')
            ->leftJoin('taxonomia.taxones as tx', 'tx.id', '=', 'te.taxon_id')
            ->leftJoin('taxonomia.localidades as loc', 'loc.id', '=', 'te.localidad_id');

        if (! $filtros->estaVacio()) {
            $query = $this->aplicarFiltros($query, $filtros);
        }

        return $query->select([
            'te.occurrence_id', 'te.codigo_catalogo', 'te.fecha_colecta',
            'te.localidad_verbatim', 'te.state_province', 'te.decimal_latitude',
            'te.decimal_longitude', 'te.lat_lon_max_error', 'te.type_status',
            'tx.nombre_cientifico', 'tx.rango', 'loc.nombre_canonico as localidad_inec',
            'loc.codigo_inec', 'ed.occurrence_id_visible', 'ed.scientific_name_visible',
            'ed.event_date_visible', 'ed.locality_name_visible', 'ed.state_province_visible',
            'ed.decimal_latitude_visible', 'ed.decimal_longitude_visible', 'ed.type_status_visible',
        ])->orderBy('te.fila_origen_excel')->orderBy('te.id')->cursor();
    }

    /** @return list<EspecimenParaArbol> */
    public function obtenerTodos(?FiltrosBusqueda $filtros = null): array
    {
        $query = DB::table('taxonomia.especimenes as te')
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'te.id');

        if ($filtros !== null && ! $filtros->estaVacio()) {
            $query = $this->aplicarFiltros($query, $filtros);
        }

        $filas = $query->select([
            'te.id',
            'te.occurrence_id',
            'te.taxon_id',
            'ed.genus_visible',
            'ed.scientific_name_visible',
        ])->orderBy('te.fila_origen_excel')->orderBy('te.id')->get();

        if ($filas->isEmpty()) {
            return [];
        }

        $taxonIds = array_values(array_filter(array_unique(array_map(fn ($f) => $f->taxon_id, $filas->all()))));
        $jerarquiasPorTaxon = $this->resolverJerarquiasPorTaxon($taxonIds);

        $result = [];

        foreach ($filas as $fila) {
            $porRango = $jerarquiasPorTaxon[$fila->taxon_id] ?? [];

            try {
                $jerarquia = JerarquiaTaxonomica::parcial(
                    phylum: $porRango[RangoTaxonomico::Phylum->rangoBD()] ?? '',
                    class: $porRango[RangoTaxonomico::Class_->rangoBD()] ?? '',
                    order: $porRango[RangoTaxonomico::Order->rangoBD()] ?? '',
                    family: $porRango[RangoTaxonomico::Family->rangoBD()] ?? '',
                    genus: $porRango[RangoTaxonomico::Genus->rangoBD()] ?? '',
                    scientificName: $porRango[RangoTaxonomico::Species->rangoBD()] ?? '',
                );

                $result[] = EspecimenParaArbol::crear(
                    occurrenceID: $fila->occurrence_id,
                    jerarquia: $jerarquia,
                    genusVisible: (bool) $fila->genus_visible,
                    scientificNameVisible: (bool) $fila->scientific_name_visible,
                    especimenId: $fila->id,
                );
            } catch (\Throwable) {
                continue;
            }
        }

        return $result;
    }

    /**
     * Resuelve, por cada taxón raíz recibido, la cadena de ancestros canónicos
     * (phylum, clase, orden, familia, género, especie) usando `rango` real.
     * Conserva las cadenas incompletas con los rangos que sí estén identificados.
     *
     * @param  list<string>  $taxonIds
     * @return array<string, array<string, string>> taxon_id → [rango_bd → nombre_cientifico]
     */
    private function resolverJerarquiasPorTaxon(array $taxonIds): array
    {
        if ($taxonIds === []) {
            return [];
        }

        $rangosCanonicos = [
            RangoTaxonomico::Species->rangoBD(),
            RangoTaxonomico::Genus->rangoBD(),
            RangoTaxonomico::Family->rangoBD(),
            RangoTaxonomico::Order->rangoBD(),
            RangoTaxonomico::Class_->rangoBD(),
            RangoTaxonomico::Phylum->rangoBD(),
        ];

        // CTE recursivo: parte de cada taxón raíz y sube por padre_id, conservando el
        // `raiz` para pivotear luego por taxón de origen y rango.
        $sql = <<<'SQL'
            WITH RECURSIVE cadena AS (
                SELECT tx.id AS raiz, tx.id, tx.rango, tx.nombre_cientifico, tx.padre_id, 0 AS profundidad
                FROM taxonomia.taxones tx
                WHERE tx.id = ANY(?)
                UNION ALL
                SELECT c.raiz, p.id, p.rango, p.nombre_cientifico, p.padre_id, c.profundidad + 1
                FROM cadena c
                JOIN taxonomia.taxones p ON p.id = c.padre_id
                WHERE c.profundidad < 20
            )
            SELECT raiz::text AS raiz, rango, nombre_cientifico
            FROM cadena
            WHERE rango = ANY(?)
        SQL;

        $filas = DB::select($sql, [
            '{'.implode(',', $taxonIds).'}',
            '{'.implode(',', $rangosCanonicos).'}',
        ]);

        /** @var array<string, array<string, string>> */
        $porTaxon = [];
        foreach ($filas as $fila) {
            $porTaxon[$fila->raiz][$fila->rango] = $fila->nombre_cientifico;
        }

        return $porTaxon;
    }

    private function aplicarFiltros(Builder $query, FiltrosBusqueda $filtros): Builder
    {
        // N.° de catálogo — multi-valor separado por coma, comparación exacta case-insensitive
        if ($filtros->codigosCatalogo !== []) {
            $placeholders = implode(',', array_fill(0, count($filtros->codigosCatalogo), '?'));
            $valores = array_map('strtolower', $filtros->codigosCatalogo);
            $query->whereRaw("LOWER(te.codigo_catalogo) = ANY(ARRAY[{$placeholders}])", $valores);
        }

        // Tipo de colección (preparations) — multi-select
        if ($filtros->preparaciones !== []) {
            $placeholders = implode(',', array_fill(0, count($filtros->preparaciones), '?'));
            $valores = array_map('strtolower', $filtros->preparaciones);
            $query->whereRaw("LOWER(te.preparations) = ANY(ARRAY[{$placeholders}])", $valores);
        }

        // Taxonomía — CTE recursivo pre-resuelto
        if ($filtros->taxonNombre !== null) {
            $ids = $this->resolverDescendientesTaxon($filtros->taxonNombre);
            if ($ids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('te.taxon_id', $ids);
            }
        }

        // Geografía — CTE recursivo pre-resuelto
        if ($filtros->geografias !== []) {
            $ids = $this->resolverDescendientesGeografia($filtros->geografias);
            if ($ids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('te.localidad_id', $ids);
            }
        }

        // Colector — búsqueda parcial case-insensitive
        if ($filtros->colectores !== []) {
            $query->where(function (Builder $q) use ($filtros): void {
                foreach ($filtros->colectores as $colector) {
                    $q->orWhere('te.colector', 'ILIKE', '%'.$colector.'%');
                }
            });
        }

        // Fecha de recolección — solapamiento de rangos
        if ($filtros->fechaHasta !== null) {
            $query->whereRaw('te.fecha_colecta <= ?', [$filtros->fechaHasta->format('Y-m-d')]);
        }
        if ($filtros->fechaDesde !== null) {
            $query->whereRaw('COALESCE(te.fecha_colecta_fin, te.fecha_colecta) >= ?', [$filtros->fechaDesde->format('Y-m-d')]);
        }

        // Método de recolección — JOIN con muestras_colecta
        if ($filtros->metodosRecoleccion !== []) {
            $query->join('taxonomia.muestras_colecta as mc', 'mc.id', '=', 'te.muestra_id');
            $placeholders = implode(',', array_fill(0, count($filtros->metodosRecoleccion), '?'));
            $valores = array_map('strtolower', $filtros->metodosRecoleccion);
            $query->whereRaw("LOWER(mc.sampling_protocol) = ANY(ARRAY[{$placeholders}])", $valores);
        }

        // Coordenadas — bounding box
        if ($filtros->latMin !== null && $filtros->latMax !== null) {
            $query->where('ed.decimal_latitude_visible', true);
            $query->whereBetween('te.decimal_latitude', [$filtros->latMin, $filtros->latMax]);
        }
        if ($filtros->lonMin !== null && $filtros->lonMax !== null) {
            $query->where('ed.decimal_longitude_visible', true);
            $query->whereBetween('te.decimal_longitude', [$filtros->lonMin, $filtros->lonMax]);
        }

        // Elevación — solapamiento de rangos
        if ($filtros->elevDesde !== null) {
            $query->whereRaw('COALESCE(te.elevation_max_m, te.elevation_min_m) >= ?', [$filtros->elevDesde]);
        }
        if ($filtros->elevHasta !== null) {
            $query->whereRaw('COALESCE(te.elevation_min_m, te.elevation_max_m) <= ?', [$filtros->elevHasta]);
        }

        // Bioma — multi-select
        if ($filtros->biomas !== []) {
            $placeholders = implode(',', array_fill(0, count($filtros->biomas), '?'));
            $valores = array_map('strtolower', $filtros->biomas);
            $query->whereRaw("LOWER(te.biome) = ANY(ARRAY[{$placeholders}])", $valores);
        }

        if ($filtros->habitat !== null) {
            $query->where(static function (Builder $q) use ($filtros): void {
                $q->where('te.habitat', 'ILIKE', '%'.$filtros->habitat.'%')
                    ->orWhere('te.microhabitat', 'ILIKE', '%'.$filtros->habitat.'%');
            });
        }
        if ($filtros->tipo !== null) {
            $query->where('ed.type_status_visible', true)
                ->where('te.type_status', 'ILIKE', '%'.$filtros->tipo.'%');
        }
        if ($filtros->casta !== null) {
            $query->where('ed.caste_visible', true)
                ->where('te.caste', 'ILIKE', '%'.$filtros->casta.'%');
        }
        if ($filtros->estadio !== null) {
            $query->where('ed.life_stage_visible', true)
                ->where('te.life_stage', 'ILIKE', '%'.$filtros->estadio.'%');
        }

        return $query;
    }

    /** @return list<string> UUIDs del taxón buscado y todos sus descendientes */
    private function resolverDescendientesTaxon(string $nombre): array
    {
        $sql = <<<'SQL'
            WITH RECURSIVE descendientes AS (
                SELECT id FROM taxonomia.taxones
                WHERE nombre_cientifico ILIKE ?
                UNION ALL
                SELECT t.id FROM taxonomia.taxones t
                JOIN descendientes d ON t.padre_id = d.id
            )
            SELECT id::text FROM descendientes
        SQL;

        return array_column(DB::select($sql, ['%'.$nombre.'%']), 'id');
    }

    /** @return list<string> UUIDs de las localidades buscadas y todos sus descendientes */
    private function resolverDescendientesGeografia(array $nombres): array
    {
        $conditions = implode(' OR ', array_fill(0, count($nombres), 'nombre_canonico ILIKE ?'));
        $params = array_map(static fn ($n) => '%'.$n.'%', $nombres);

        $sql = <<<SQL
            WITH RECURSIVE descendientes AS (
                SELECT id FROM taxonomia.localidades
                WHERE {$conditions}
                UNION ALL
                SELECT l.id FROM taxonomia.localidades l
                JOIN descendientes d ON l.padre_id = d.id
            )
            SELECT id::text FROM descendientes
        SQL;

        return array_column(DB::select($sql, $params), 'id');
    }
}
