<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PortalEstadisticas
{
    public function __invoke(Request $request): RedirectResponse
    {
        $filtros = $request->validate($this->reglasFiltros());
        $this->validarPeriodo($filtros);
        if (isset($filtros['colector'])) {
            $filtros['colector'] = trim($filtros['colector']);
        }
        $filtros = array_filter($filtros, static fn ($valor) => $valor !== null && $valor !== '');
        $filtros = $this->normalizarOpciones($filtros);
        return redirect()->route('portal.catalogo', array_filter([
            'vista' => 'mapa',
            'ft' => $filtros['taxon'] ?? null,
            'fprov' => $filtros['provincia'] ?? null,
            'fph' => $filtros['filo'] ?? null,
            'ffd' => isset($filtros['desde']) ? $filtros['desde'].'-01-01' : null,
            'ffh' => isset($filtros['hasta']) ? $filtros['hasta'].'-12-31' : null,
            'fmes' => $filtros['mes'] ?? null,
            'fid' => $filtros['identificacion'] ?? null,
            'fgeo' => $filtros['ubicacion'] ?? null,
            'fap' => ($filtros['aptitud'] ?? null) === 'completos' ? '1' : null,
            'fco' => $filtros['colector'] ?? null,
            'fm' => isset($filtros['metodo']) ? [$filtros['metodo']] : null,
        ], static fn ($valor) => $valor !== null && $valor !== ''));
    }

    /** Agregados de la misma selección que usan tarjetas y registros. */
    public function datosParaVista(array $filtros): array
    {
        return $this->cachear('portal:estadisticas:v8:'.sha1(json_encode($filtros)), fn () => $this->resumir($filtros));
    }

    public function descargarLista(Request $request): StreamedResponse
    {
        $filtros = $request->validate($this->reglasFiltros());
        $this->validarPeriodo($filtros);
        if (isset($filtros['colector'])) {
            $filtros['colector'] = trim($filtros['colector']);
        }
        $filtros = array_filter($filtros, static fn ($valor) => $valor !== null && $valor !== '');
        $filtros = $this->normalizarOpciones($filtros);
        return $this->descargarListaConFiltros($filtros);
    }

    public function descargarListaConFiltros(array $filtros): StreamedResponse
    {
        $consulta = $this->consulta($filtros)
            ->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->where('t.rango', 'especie')->where('d.scientific_name_visible', true)
            ->selectRaw('t.nombre_cientifico AS especie, COUNT(*) AS registros')
            ->groupBy('t.nombre_cientifico')->orderBy('t.nombre_cientifico');

        return response()->streamDownload(static function () use ($consulta): void {
            $salida = fopen('php://output', 'wb');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Especie', 'Registros'], ';', '"', '');
            foreach ($consulta->cursor() as $fila) {
                $nombre = (string) $fila->especie;
                if (preg_match('/^[=+\-@]/', $nombre)) {
                    $nombre = "'".$nombre;
                }
                fputcsv($salida, [$nombre, (int) $fila->registros], ';', '"', '');
            }
            fclose($salida);
        }, 'especies-coleccion.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function consulta(array $filtros): Builder
    {
        $query = DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true);
        if (! empty($filtros['codigo'])) {
            $codigos = array_slice(array_values(array_filter(array_map('trim', explode(',', (string) $filtros['codigo'])))), 0, 25);
            $query->where('d.occurrence_id_visible', true)->whereIn(DB::raw('LOWER(e.codigo_catalogo)'), array_map('mb_strtolower', $codigos));
        }
        if (! empty($filtros['preparaciones'])) {
            $query->whereIn(DB::raw('LOWER(e.preparations)'), array_map('mb_strtolower', $filtros['preparaciones']));
        }
        if (! empty($filtros['provincia'])) {
            $query->where('d.state_province_visible', true)->where('e.state_province', $filtros['provincia']);
        }
        if (! empty($filtros['geografias'])) {
            $nombres = array_slice(array_values(array_filter(array_map('trim', $filtros['geografias']))), 0, 10);
            if ($nombres !== []) {
                $condiciones = implode(' OR ', array_fill(0, count($nombres), 'nombre_canonico ILIKE ?'));
                $ids = array_column(DB::select("WITH RECURSIVE descendientes AS (SELECT id FROM taxonomia.localidades WHERE {$condiciones} UNION ALL SELECT l.id FROM taxonomia.localidades l JOIN descendientes d ON l.padre_id = d.id) SELECT id::text FROM descendientes", array_map(static fn (string $nombre): string => '%'.$nombre.'%', $nombres)), 'id');
                $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('e.localidad_id', $ids);
            }
        }
        if (isset($filtros['desde'])) {
            $query->where('d.event_date_visible', true)->whereYear('e.fecha_colecta', '>=', (int) $filtros['desde']);
        }
        if (isset($filtros['hasta'])) {
            $query->where('d.event_date_visible', true)->whereYear('e.fecha_colecta', '<=', (int) $filtros['hasta']);
        }
        if (! empty($filtros['desde_fecha'])) {
            $query->where('d.event_date_visible', true)->whereRaw('COALESCE(e.fecha_colecta_fin, e.fecha_colecta) >= ?', [$filtros['desde_fecha']]);
        }
        if (! empty($filtros['hasta_fecha'])) {
            $query->where('d.event_date_visible', true)->whereDate('e.fecha_colecta', '<=', $filtros['hasta_fecha']);
        }
        if (! empty($filtros['filo'])) {
            $query->whereRaw('e.taxon_id IN (WITH RECURSIVE descendientes AS (SELECT id FROM taxonomia.taxones WHERE id = ? UNION SELECT t.id FROM taxonomia.taxones t JOIN descendientes d ON t.padre_id = d.id) SELECT id FROM descendientes)', [$filtros['filo']]);
        }
        if (! empty($filtros['taxon'])) {
            $literal = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($filtros['taxon']));
            $query->where('d.scientific_name_visible', true)
                ->whereRaw('e.taxon_id IN (WITH RECURSIVE descendientes AS (SELECT id FROM taxonomia.taxones WHERE nombre_cientifico ILIKE ? UNION SELECT t.id FROM taxonomia.taxones t JOIN descendientes d ON t.padre_id = d.id) SELECT id FROM descendientes)', ['%'.$literal.'%']);
        }
        if (! empty($filtros['mes'])) {
            $query->where('d.event_date_visible', true)->whereMonth('e.fecha_colecta', (int) $filtros['mes']);
        }
        if (! empty($filtros['colector'])) {
            $literal = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($filtros['colector']));
            $query->where('d.recorded_by_visible', true)->where('e.colector', 'ILIKE', '%'.$literal.'%');
        }
        if (! empty($filtros['metodo'])) {
            $query->where('d.sampling_protocol_visible', true)
                ->whereExists(static fn (Builder $subconsulta) => $subconsulta->selectRaw('1')->from('taxonomia.muestras_colecta as metodo')
                    ->whereColumn('metodo.id', 'e.muestra_id')->where('metodo.sampling_protocol', $filtros['metodo']));
        }
        if (! empty($filtros['metodos'])) {
            $metodos = array_map('mb_strtolower', $filtros['metodos']);
            $query->where('d.sampling_protocol_visible', true)->whereExists(static fn (Builder $subconsulta) => $subconsulta->selectRaw('1')
                ->from('taxonomia.muestras_colecta as metodo')->whereColumn('metodo.id', 'e.muestra_id')
                ->whereIn(DB::raw('LOWER(metodo.sampling_protocol)'), $metodos));
        }
        if (isset($filtros['lat_min'], $filtros['lat_max'])) {
            $query->where('d.decimal_latitude_visible', true)->whereBetween('e.decimal_latitude', [$filtros['lat_min'], $filtros['lat_max']]);
        }
        if (isset($filtros['lon_min'], $filtros['lon_max'])) {
            $query->where('d.decimal_longitude_visible', true)->whereBetween('e.decimal_longitude', [$filtros['lon_min'], $filtros['lon_max']]);
        }
        if (isset($filtros['elev_desde'])) {
            $query->where('d.elevation_visible', true)->whereRaw('COALESCE(e.elevation_max_m, e.elevation_min_m) >= ?', [$filtros['elev_desde']]);
        }
        if (isset($filtros['elev_hasta'])) {
            $query->where('d.elevation_visible', true)->whereRaw('COALESCE(e.elevation_min_m, e.elevation_max_m) <= ?', [$filtros['elev_hasta']]);
        }
        if (! empty($filtros['biomas'])) {
            $query->whereIn(DB::raw('LOWER(e.biome)'), array_map('mb_strtolower', $filtros['biomas']));
        }
        if (! empty($filtros['habitat'])) {
            $query->where(static fn (Builder $q) => $q->where('e.habitat', 'ILIKE', '%'.$filtros['habitat'].'%')
                ->orWhere('e.microhabitat', 'ILIKE', '%'.$filtros['habitat'].'%'));
        }
        foreach (['tipo' => ['type_status', 'type_status_visible'], 'casta' => ['caste', 'caste_visible'], 'estadio' => ['life_stage', 'life_stage_visible']] as $clave => [$columna, $visibilidad]) {
            if (! empty($filtros[$clave])) {
                $query->where('d.'.$visibilidad, true)->where('e.'.$columna, 'ILIKE', '%'.$filtros[$clave].'%');
            }
        }
        if (($filtros['identificacion'] ?? '') === 'especie') {
            $query->where('d.scientific_name_visible', true)
                ->whereExists(static fn (Builder $subconsulta) => $subconsulta->selectRaw('1')->from('taxonomia.taxones as identificacion')->whereColumn('identificacion.id', 'e.taxon_id')->where('identificacion.rango', 'especie'));
        }
        if (($filtros['identificacion'] ?? '') === 'superior') {
            $query->where('d.scientific_name_visible', true)
                ->whereExists(static fn (Builder $subconsulta) => $subconsulta->selectRaw('1')->from('taxonomia.taxones as identificacion')->whereColumn('identificacion.id', 'e.taxon_id')->where('identificacion.rango', '<>', 'especie'));
        }
        if (($filtros['ubicacion'] ?? '') === '1') {
            $query->where('d.decimal_latitude_visible', true)->where('d.decimal_longitude_visible', true)
                ->whereNotNull('e.decimal_latitude')->whereNotNull('e.decimal_longitude');
        }
        if (($filtros['aptitud'] ?? '') === 'completos') {
            $query->where('d.scientific_name_visible', true)->where('d.event_date_visible', true)
                ->where('d.decimal_latitude_visible', true)->where('d.decimal_longitude_visible', true)
                ->whereNotNull('e.fecha_colecta')->whereNotNull('e.decimal_latitude')->whereNotNull('e.decimal_longitude')
                ->whereExists(static fn (Builder $subconsulta) => $subconsulta->selectRaw('1')->from('taxonomia.taxones as apto')
                    ->whereColumn('apto.id', 'e.taxon_id')->where('apto.rango', 'especie'));
        }

        return $query;
    }

    private function reglasFiltros(): array
    {
        return [
            'provincia' => ['nullable', 'string', 'max:120'],
            'desde' => ['nullable', 'integer', 'between:1800,2100'],
            'hasta' => ['nullable', 'integer', 'between:1800,2100'],
            'filo' => ['nullable', 'uuid'],
            'taxon' => ['nullable', 'string', 'max:120'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'aptitud' => ['nullable', 'in:completos'],
            'colector' => ['nullable', 'string', 'max:120'],
            'metodo' => ['nullable', 'string', 'max:255'],
            'identificacion' => ['nullable', 'in:especie,superior'],
            'ubicacion' => ['nullable', 'in:1'],
        ];
    }

    private function normalizarOpciones(array $filtros): array
    {
        if (isset($filtros['provincia']) && ! DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->where('d.state_province_visible', true)
            ->where('e.state_province', $filtros['provincia'])->exists()) {
            unset($filtros['provincia']);
        }
        if (isset($filtros['filo']) && ! DB::table('taxonomia.taxones')->where('id', $filtros['filo'])->where('rango', 'phylum')->exists()) {
            unset($filtros['filo']);
        }
        if (isset($filtros['metodo']) && ! DB::table('taxonomia.muestras_colecta as m')
            ->join('taxonomia.especimenes as e', 'e.muestra_id', '=', 'm.id')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->where('d.sampling_protocol_visible', true)
            ->where('m.sampling_protocol', $filtros['metodo'])->exists()) {
            unset($filtros['metodo']);
        }

        return $filtros;
    }

    private function validarPeriodo(array $filtros): void
    {
        if (isset($filtros['desde'], $filtros['hasta']) && (int) $filtros['desde'] > (int) $filtros['hasta']) {
            throw ValidationException::withMessages(['hasta' => 'El año final debe ser igual o posterior al inicial.']);
        }
    }

    /** El panel sigue disponible si falla el almacén de caché; los errores SQL sí se propagan. */
    private function cachear(string $clave, callable $calcular): array
    {
        try {
            $guardado = Cache::get($clave);
            if (is_array($guardado)) {
                return $guardado;
            }
        } catch (\Throwable $error) {
            Log::warning('Caché de estadísticas no disponible', ['operacion' => 'leer', 'tipo' => $error::class]);
        }

        $resultado = $calcular();
        try {
            Cache::put($clave, $resultado, 300);
        } catch (\Throwable $error) {
            Log::warning('Caché de estadísticas no disponible', ['operacion' => 'guardar', 'tipo' => $error::class]);
        }

        return $resultado;
    }

    private function resumir(array $filtros): array
    {
        $base = $this->consulta($filtros);
        $resumen = (array) (clone $base)->leftJoin('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->selectRaw("COUNT(*) AS registros, COUNT(*) FILTER (WHERE t.rango = 'especie' AND d.scientific_name_visible) AS identificados, COUNT(*) FILTER (WHERE e.fecha_colecta IS NOT NULL AND d.event_date_visible) AS fechados, COUNT(*) FILTER (WHERE e.decimal_latitude IS NOT NULL AND e.decimal_longitude IS NOT NULL AND d.decimal_latitude_visible AND d.decimal_longitude_visible) AS georreferenciados, COUNT(*) FILTER (WHERE t.rango = 'especie' AND d.scientific_name_visible AND e.fecha_colecta IS NOT NULL AND d.event_date_visible AND e.decimal_latitude IS NOT NULL AND e.decimal_longitude IS NOT NULL AND d.decimal_latitude_visible AND d.decimal_longitude_visible) AS aptos")
            ->first();

        $porTaxon = (clone $base)->where('d.scientific_name_visible', true)->selectRaw('e.taxon_id, COUNT(*) AS total')
            ->groupBy('e.taxon_id')->get();
        $taxones = DB::table('taxonomia.taxones')->select('id', 'padre_id', 'rango', 'nombre_cientifico')
            ->get()->keyBy('id');
        $filos = [];
        foreach ($porTaxon as $fila) {
            $id = $fila->taxon_id;
            $filo = 'Sin filo';
            for ($paso = 0; $paso < 20 && $id && isset($taxones[$id]); $paso++) {
                $taxon = $taxones[$id];
                if ($taxon->rango === 'phylum') {
                    $filo = $taxon->nombre_cientifico;
                    break;
                }
                $id = $taxon->padre_id;
            }
            $filos[$filo] = ($filos[$filo] ?? 0) + (int) $fila->total;
        }
        unset($filos['Sin filo']);
        arsort($filos);

        $especies = (clone $base)->join('taxonomia.taxones as t', 't.id', '=', 'e.taxon_id')
            ->where('t.rango', 'especie')->where('d.scientific_name_visible', true)
            ->selectRaw('t.nombre_cientifico AS nombre, COUNT(*) AS total')
            ->groupBy('t.nombre_cientifico')->orderByDesc('total')->limit(20)->get()->map(static fn (object $fila): array => (array) $fila)->all();
        $riqueza = (clone $base)->join('taxonomia.taxones as riqueza_t', 'riqueza_t.id', '=', 'e.taxon_id')
            ->where('riqueza_t.rango', 'especie')->where('d.scientific_name_visible', true)
            ->where('d.state_province_visible', true)->whereNotNull('e.state_province')->where('e.state_province', '<>', '')
            ->selectRaw('e.state_province AS provincia, COUNT(DISTINCT riqueza_t.nombre_cientifico) AS especies, COUNT(*) AS registros')
            ->groupBy('e.state_province')->orderByDesc('especies')->limit(10)->get()->map(static fn (object $fila): array => (array) $fila)->all();
        $decadas = (clone $base)->join('taxonomia.taxones as decada_t', 'decada_t.id', '=', 'e.taxon_id')
            ->where('decada_t.rango', 'especie')->where('d.scientific_name_visible', true)
            ->where('d.event_date_visible', true)->whereNotNull('e.fecha_colecta')
            ->selectRaw('FLOOR(EXTRACT(YEAR FROM e.fecha_colecta) / 10)::integer * 10 AS decada, COUNT(DISTINCT decada_t.nombre_cientifico) AS especies, COUNT(*) AS registros')
            ->groupByRaw('1')->orderBy('decada')->get()->map(static fn (object $fila): array => (array) $fila)->all();
        $raras = (clone $base)->join('taxonomia.taxones as rara_t', 'rara_t.id', '=', 'e.taxon_id')
            ->where('rara_t.rango', 'especie')->where('d.scientific_name_visible', true)
            ->selectRaw('rara_t.nombre_cientifico AS nombre, COUNT(*) AS total')
            ->groupBy('rara_t.nombre_cientifico')->havingRaw('COUNT(*) <= 3')
            ->orderBy('total')->orderBy('nombre')->limit(12)->get()->map(static fn (object $fila): array => (array) $fila)->all();

        // Celdas de 0,25 grados: el navegador recibe centenares de círculos,
        // nunca las decenas de miles de coordenadas individuales.
        $mapaPorTaxon = (clone $base)->where('d.decimal_latitude_visible', true)
            ->where('d.decimal_longitude_visible', true)
            ->whereNotNull('e.decimal_latitude')->whereNotNull('e.decimal_longitude')
            ->whereBetween('e.decimal_latitude', [-90, 90])->whereBetween('e.decimal_longitude', [-180, 180])
            ->selectRaw('ROUND((e.decimal_latitude * 4)::numeric) / 4 AS lat, ROUND((e.decimal_longitude * 4)::numeric) / 4 AS lon, e.taxon_id, d.scientific_name_visible, COUNT(*) AS total')
            ->groupByRaw('1, 2, 3, 4')->get();
        $celdas = [];
        foreach ($mapaPorTaxon as $fila) {
            $id = $fila->scientific_name_visible ? $fila->taxon_id : null;
            $filo = 'Sin filo';
            for ($paso = 0; $paso < 20 && $id && isset($taxones[$id]); $paso++) {
                $taxon = $taxones[$id];
                if ($taxon->rango === 'phylum') {
                    $filo = $taxon->nombre_cientifico;
                    break;
                }
                $id = $taxon->padre_id;
            }
            $clave = $fila->lat.':'.$fila->lon;
            $celdas[$clave] ??= ['lat' => $fila->lat, 'lon' => $fila->lon, 'total' => 0, 'filos' => []];
            $celdas[$clave]['total'] += (int) $fila->total;
            $celdas[$clave]['filos'][$filo] = ($celdas[$clave]['filos'][$filo] ?? 0) + (int) $fila->total;
        }
        uasort($celdas, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        $mapa = array_values($celdas);

        return [
            'resumen' => $resumen,
            'filos' => $filos,
            'especies' => $especies,
            'riqueza' => $riqueza,
            'decadas' => $decadas,
            'raras' => $raras,
            'mapa' => $mapa,
        ];
    }
}
