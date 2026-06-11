<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenParaArbol;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;

final class EloquentProveedorEspecimenesParaArbol implements ProveedorEspecimenesParaArbolPort
{
    /** @return list<EspecimenParaArbol> */
    public function obtenerTodos(?FiltrosBusqueda $filtros = null): array
    {
        $query = DB::table('taxonomia.especimenes as te')
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'te.id')
            ->join('taxonomia.taxones as tx_species', 'tx_species.id', '=', 'te.taxon_id')
            ->leftJoin('taxonomia.taxones as tx_genus', 'tx_genus.id', '=', 'tx_species.padre_id')
            ->leftJoin('taxonomia.taxones as tx_family', 'tx_family.id', '=', 'tx_genus.padre_id')
            ->leftJoin('taxonomia.taxones as tx_order', 'tx_order.id', '=', 'tx_family.padre_id')
            ->leftJoin('taxonomia.taxones as tx_class', 'tx_class.id', '=', 'tx_order.padre_id')
            ->leftJoin('taxonomia.taxones as tx_phylum', 'tx_phylum.id', '=', 'tx_class.padre_id')
            ->whereNotNull('tx_genus.id')
            ->whereNotNull('tx_family.id')
            ->whereNotNull('tx_order.id')
            ->whereNotNull('tx_class.id')
            ->whereNotNull('tx_phylum.id');

        if ($filtros !== null && ! $filtros->estaVacio()) {
            $query = $this->aplicarFiltros($query, $filtros);
        }

        $filas = $query->select([
            'te.occurrence_id',
            'tx_species.nombre_cientifico as scientific_name',
            'tx_genus.nombre_cientifico as genus',
            'tx_family.nombre_cientifico as family',
            'tx_order.nombre_cientifico as order',
            'tx_class.nombre_cientifico as class',
            'tx_phylum.nombre_cientifico as phylum',
            'ed.genus_visible',
            'ed.scientific_name_visible',
        ])->get();

        $result = [];

        foreach ($filas as $fila) {
            $genus = $fila->genus;

            $scientificName = str_starts_with($fila->scientific_name, $genus.' ')
                ? $fila->scientific_name
                : $genus.' '.$fila->scientific_name;

            $specificEpithet = substr($scientificName, strlen($genus) + 1);

            try {
                $jerarquia = JerarquiaTaxonomica::desde(
                    phylum: $fila->phylum,
                    class: $fila->class,
                    order: $fila->order,
                    family: $fila->family,
                    genus: $genus,
                    specificEpithet: $specificEpithet,
                    scientificName: $scientificName,
                );

                $result[] = EspecimenParaArbol::crear(
                    occurrenceID: $fila->occurrence_id,
                    jerarquia: $jerarquia,
                    genusVisible: (bool) $fila->genus_visible,
                    scientificNameVisible: (bool) $fila->scientific_name_visible,
                );
            } catch (\Throwable) {
                continue;
            }
        }

        return $result;
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
            $query->whereBetween('te.decimal_latitude', [$filtros->latMin, $filtros->latMax]);
        }
        if ($filtros->lonMin !== null && $filtros->lonMax !== null) {
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
