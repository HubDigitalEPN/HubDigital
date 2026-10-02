<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;
use Modules\CatalogoPublico\Infrastructure\NormalizacionGeografica;
use Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenParaArbol;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

final class EloquentProveedorEspecimenesParaArbol implements ProveedorEspecimenesParaArbolPort
{
    /** Única selección pública para árbol, tabla, mapa y exportaciones. */
    public function consultaPublica(FiltrosBusqueda $filtros, string $nivel = '', string $taxon = ''): Builder
    {
        $query = DB::table('taxonomia.especimenes as te')
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'te.id')
            ->where('ed.publicado', true)->where('te.coordenadas_otras_regiones', false);
        $this->aplicarFiltros($query, $filtros);
        $rangos = ['phylum' => 'phylum', 'class' => 'clase', 'order' => 'orden', 'family' => 'familia', 'genus' => 'genero', 'species' => 'especie'];
        if (isset($rangos[$nivel]) && $taxon !== '') {
            if ($nivel === 'family') $query->where('ed.family_visible', true);
            if ($nivel === 'genus') $query->where('ed.genus_visible', true);
            $query->where('ed.scientific_name_visible', true)->whereRaw('te.taxon_id IN (WITH RECURSIVE seleccion AS (SELECT id FROM taxonomia.taxones WHERE rango = ? AND nombre_cientifico = ? UNION SELECT t.id FROM taxonomia.taxones t JOIN seleccion s ON t.padre_id = s.id) SELECT id FROM seleccion)', [$rangos[$nivel], $taxon]);
        }

        return $query;
    }

    public function paginaPublica(FiltrosBusqueda $filtros, int $pagina, string $nivel = '', string $taxon = ''): array
    {
        $query = $this->consultaPublica($filtros, $nivel, $taxon);
        $total = (clone $query)->count('te.id');
        $ultima = max(1, (int) ceil($total / 12));
        $actual = min(max(1, $pagina), $ultima);
        $ids = $query->orderBy('te.fila_origen_excel')->orderBy('te.id')
            ->offset(($actual - 1) * 12)->limit(12)->pluck('te.id')->all();

        return ['ids' => $ids, 'total' => $total, 'pagina' => $actual, 'ultima' => $ultima];
    }

    /** Tarjetas iniciales: una fila por taxón, sin materializar todos los ejemplares. */
    public function resumenRaiz(FiltrosBusqueda $filtros): array
    {
        $resumen = $this->resumenJerarquia($filtros);
        $hijos = array_values(array_filter($resumen['nodos'], static fn (array $n): bool => $n['nivel'] === 'phylum'));
        $conteos = array_intersect_key($resumen['conteos'], array_fill_keys(array_map(static fn (array $n): string => 'phylum:'.$n['taxon'], $hijos), true));
        return ['hijos' => $hijos,
            'conteos' => $conteos, 'descendientes' => $resumen['descendientes']];
    }

    /** Navegación agregada por taxón: evita hidratar la colección completa en una VM pequeña. */
    public function resumenJerarquia(FiltrosBusqueda $filtros, string $nivel = '', string $taxon = ''): array
    {
        $filas = $this->consultaPublica($filtros, $nivel, $taxon)->where('ed.scientific_name_visible', true)
            ->selectRaw('te.taxon_id, ed.family_visible, ed.genus_visible, COUNT(*) AS total')
            ->groupBy('te.taxon_id', 'ed.family_visible', 'ed.genus_visible')->get();
        $taxones = DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'nombre_cientifico', 'rango'])->keyBy('id');
        $rangos = ['phylum' => 'phylum', 'clase' => 'class', 'orden' => 'order', 'familia' => 'family', 'genero' => 'genus', 'especie' => 'species'];
        $nodos = $especies = $conteos = $distintos = $rutas = [];
        $total = $this->consultaPublica($filtros, $nivel, $taxon)->count('te.id');
        foreach ($filas as $fila) {
            $ruta = [];
            $id = $fila->taxon_id;
            $visitados = [];
            while ($id && isset($taxones[$id]) && ! isset($visitados[$id]) && count($visitados) < 30) {
                $visitados[$id] = true;
                $t = $taxones[$id];
                if (isset($rangos[$t->rango]) && ($t->rango !== 'familia' || $fila->family_visible)
                    && ($t->rango !== 'genero' || $fila->genus_visible)) {
                    array_unshift($ruta, ['id' => $id, 'nivel' => $rangos[$t->rango], 'taxon' => $t->nombre_cientifico]);
                }
                $id = $t->padre_id;
            }
            if ($ruta === [] || $ruta[0]['nivel'] !== 'phylum') continue;
            $padre = 'root';
            $genus = '';
            foreach ($ruta as $i => $nodo) {
                $clave = $nodo['nivel'].':'.$nodo['taxon'];
                $claveRama = $clave.':padre:'.$padre;
                $conteos[$clave] = ($conteos[$clave] ?? 0) + (int) $fila->total;
                $rutaPublica = array_slice($ruta, 0, $i + 1);
                $rutaAnterior = $rutas[$clave] ?? [];
                if (count($rutaPublica) > count($rutaAnterior) || (count($rutaPublica) === count($rutaAnterior)
                    && strcmp(implode(':', array_column($rutaPublica, 'id')), implode(':', array_column($rutaAnterior, 'id'))) < 0)) $rutas[$clave] = $rutaPublica;
                if ($nodo['nivel'] === 'species') {
                    $especies[$claveRama] ??= ['id' => $nodo['id'], 'especie' => $nodo['taxon'], 'genus' => $genus, 'specificEpithet' => '', 'padre' => $padre, 'total' => 0];
                    $especies[$claveRama]['total'] += (int) $fila->total;
                } else {
                    $nodos[$claveRama] ??= $nodo + ['padre' => $padre, 'total' => 0];
                    $nodos[$claveRama]['total'] += (int) $fila->total;
                    if ($nodo['nivel'] === 'genus') $genus = $nodo['taxon'];
                }
                foreach (array_slice($ruta, $i + 1) as $desc) $distintos[$clave][$desc['nivel']][$desc['taxon']] = true;
                $padre = $nodo['taxon'];
            }
        }
        $ordenar = static fn (array $a, array $b): int => strnatcasecmp($a['taxon'] ?? $a['especie'], $b['taxon'] ?? $b['especie']);
        uasort($nodos, $ordenar);
        uasort($especies, $ordenar);
        $descendientes = [];
        foreach ($distintos as $clave => $niveles) $descendientes[$clave] = array_map('count', $niveles);

        return ['nodos' => array_values($nodos), 'especies' => array_values($especies), 'conteos' => $conteos,
            'descendientes' => $descendientes, 'rutas' => $rutas, 'total' => $total];
    }

    /** Filas filtradas y en orden estable para una descarga CSV de memoria acotada. */
    public function cursorParaCsv(FiltrosBusqueda $filtros, string $nivel = '', string $taxon = ''): LazyCollection
    {
        $query = $this->consultaPublica($filtros, $nivel, $taxon)
            ->leftJoin('taxonomia.taxones as tx', 'tx.id', '=', 'te.taxon_id')
            ->leftJoin('taxonomia.localidades as loc', 'loc.id', '=', 'te.localidad_id');

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
            ->join('divulgacion.especimenes_divulgables as ed', 'ed.especimen_id', '=', 'te.id')
            ->where('ed.publicado', true)->where('te.coordenadas_otras_regiones', false);

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
        $jerarquias = [];

        foreach ($filas as $fila) {
            $porRango = $jerarquiasPorTaxon[$fila->taxon_id] ?? [];

            try {
                $jerarquia = $jerarquias[$fila->taxon_id ?? ''] ??= JerarquiaTaxonomica::parcial(
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
            $query->where('ed.occurrence_id_visible', true);
            $placeholders = implode(',', array_fill(0, count($filtros->codigosCatalogo), '?'));
            $valores = array_map('strtolower', $filtros->codigosCatalogo);
            $query->whereRaw("(LOWER(te.codigo_catalogo) = ANY(ARRAY[{$placeholders}]) OR LOWER(te.occurrence_id) = ANY(ARRAY[{$placeholders}]))", [...$valores, ...$valores]);
        }

        // Tipo de colección (preparations) — multi-select
        if ($filtros->preparaciones !== []) {
            $placeholders = implode(',', array_fill(0, count($filtros->preparaciones), '?'));
            $valores = array_map('strtolower', $filtros->preparaciones);
            $query->whereRaw("LOWER(te.preparations) = ANY(ARRAY[{$placeholders}])", $valores);
        }

        // Cada coincidencia textual conserva los permisos del rango que inició
        // la búsqueda; una familia reservada no puede inferirse por sus conteos.
        if ($filtros->taxonNombre !== null) {
            $query->where('ed.scientific_name_visible', true)->whereRaw(<<<'SQL'
                EXISTS (
                    WITH RECURSIVE seleccion AS (
                        SELECT id, rango AS rango_raiz FROM taxonomia.taxones WHERE nombre_cientifico ILIKE ?
                        UNION
                        SELECT t.id, s.rango_raiz FROM taxonomia.taxones t JOIN seleccion s ON t.padre_id = s.id
                    ) SELECT 1 FROM seleccion s WHERE s.id = te.taxon_id
                        AND (s.rango_raiz <> 'familia' OR ed.family_visible)
                        AND (s.rango_raiz <> 'genero' OR ed.genus_visible)
                )
                SQL, ['%'.$filtros->taxonNombre.'%']);
        }

        if ($filtros->filoId !== null) {
            $query->where('ed.scientific_name_visible', true);
            $query->whereRaw('te.taxon_id IN (WITH RECURSIVE descendientes AS (SELECT id FROM taxonomia.taxones WHERE id = ? UNION SELECT t.id FROM taxonomia.taxones t JOIN descendientes d ON t.padre_id = d.id) SELECT id FROM descendientes)', [$filtros->filoId]);
        }

        // Geografía — CTE recursivo pre-resuelto
        if ($filtros->geografias !== []) {
            $query->where('ed.locality_name_visible', true);
            $ids = $this->resolverDescendientesGeografia($filtros->geografias);
            $query->where(function (Builder $geografia) use ($ids, $filtros): void {
                $geografia->whereIn('te.localidad_id', $ids);
                foreach ($filtros->geografias as $nombre) $geografia->orWhereRaw(NormalizacionGeografica::sql('te.locality_name').' = ?', [NormalizacionGeografica::normalizar($nombre)]);
            });
        }

        if ($filtros->pais !== null) $query->where('ed.country_visible', true)->whereRaw(NormalizacionGeografica::sql('te.country').' = ?', [NormalizacionGeografica::normalizar($filtros->pais)]);

        if ($filtros->provincia !== null) {
            $query->where('ed.state_province_visible', true)->whereRaw(NormalizacionGeografica::sql('te.state_province').' = ?', [NormalizacionGeografica::normalizar($filtros->provincia)]);
        }

        // Colector — búsqueda parcial case-insensitive
        if ($filtros->colectores !== []) {
            $query->where('ed.recorded_by_visible', true);
            $query->where(function (Builder $q) use ($filtros): void {
                foreach ($filtros->colectores as $colector) {
                    $q->orWhere('te.colector', 'ILIKE', '%'.$colector.'%');
                }
            });
        }

        // Fecha de recolección — solapamiento de rangos
        if ($filtros->fechaHasta !== null) {
            $query->where('ed.event_date_visible', true);
            $query->whereRaw('te.fecha_colecta <= ?', [$filtros->fechaHasta->format('Y-m-d')]);
        }
        if ($filtros->fechaDesde !== null) {
            $query->where('ed.event_date_visible', true);
            $query->whereRaw('COALESCE(te.fecha_colecta_fin, te.fecha_colecta) >= ?', [$filtros->fechaDesde->format('Y-m-d')]);
        }
        if ($filtros->mes !== null) {
            $query->where('ed.event_date_visible', true)->whereMonth('te.fecha_colecta', $filtros->mes);
        }

        if ($filtros->identificacion !== null) {
            $query->where('ed.scientific_name_visible', true)
                ->whereExists(static function (Builder $subquery) use ($filtros): void {
                    $subquery->selectRaw('1')->from('taxonomia.taxones as identificacion')
                        ->whereColumn('identificacion.id', 'te.taxon_id');
                    if ($filtros->identificacion === 'especie') {
                        $subquery->where('identificacion.rango', 'especie')->whereRaw(CalidadDatoPublico::textoValido('identificacion.nombre_cientifico'));
                    } else {
                        $subquery->where('identificacion.rango', '<>', 'especie');
                    }
                });
        }

        if ($filtros->soloUbicacion || $filtros->datosCompletos) {
            $query->where('ed.decimal_latitude_visible', true)->where('ed.decimal_longitude_visible', true)
                ->whereBetween('te.decimal_latitude', [-90, 90])->whereBetween('te.decimal_longitude', [-180, 180]);
        }
        if ($filtros->datosCompletos) {
            $query->where('ed.scientific_name_visible', true)->where('ed.event_date_visible', true)
                ->whereRaw(CalidadDatoPublico::fechaValida('te.fecha_colecta'))
                ->whereExists(static fn (Builder $subquery) => $subquery->selectRaw('1')
                    ->from('taxonomia.taxones as apto')->whereColumn('apto.id', 'te.taxon_id')
                    ->where('apto.rango', 'especie')->whereRaw(CalidadDatoPublico::textoValido('apto.nombre_cientifico')));
        }

        // Método de recolección — JOIN con muestras_colecta
        if ($filtros->metodosRecoleccion !== []) {
            $query->leftJoin('taxonomia.muestras_colecta as mc', 'mc.id', '=', 'te.muestra_id');
            $query->where('ed.sampling_protocol_visible', true);
            $placeholders = implode(',', array_fill(0, count($filtros->metodosRecoleccion), '?'));
            $valores = array_map('strtolower', $filtros->metodosRecoleccion);
            $protocolo = ProtocoloColectaPublico::sql();
            $query->whereRaw("LOWER({$protocolo}) = ANY(ARRAY[{$placeholders}])", $valores);
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
            $query->where('ed.elevation_visible', true);
            $query->whereRaw('COALESCE(te.elevation_max_m, te.elevation_min_m) >= ?', [$filtros->elevDesde]);
        }
        if ($filtros->elevHasta !== null) {
            $query->where('ed.elevation_visible', true);
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

    /** @return list<string> UUIDs de las localidades buscadas y todos sus descendientes */
    private function resolverDescendientesGeografia(array $nombres): array
    {
        $conditions = implode(' OR ', array_fill(0, count($nombres), NormalizacionGeografica::sql('nombre_canonico').' LIKE ?'));
        $params = array_map(static fn ($n) => '%'.NormalizacionGeografica::normalizar($n).'%', $nombres);

        $sql = <<<SQL
            WITH RECURSIVE descendientes AS (
                SELECT id FROM taxonomia.localidades
                WHERE {$conditions}
                UNION
                SELECT l.id FROM taxonomia.localidades l
                JOIN descendientes d ON l.padre_id = d.id
            )
            SELECT id::text FROM descendientes
        SQL;

        return array_column(DB::select($sql, $params), 'id');
    }
}
