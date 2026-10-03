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
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;
use Modules\CatalogoPublico\Infrastructure\Adapters\StorageImagenesAdapter;
use Modules\CatalogoPublico\Infrastructure\ProtocoloColectaPublico;
use Modules\CatalogoPublico\Infrastructure\NormalizacionGeografica;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
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
        return $this->cachear('portal:estadisticas:v13:'.$this->revisionDatos().':'.sha1(json_encode($filtros)), fn () => $this->resumir($filtros));
    }

    /** Distribución completa sin hidratar tarjetas ni calcular los otros indicadores. */
    public function puntosParaMapa(array $filtros): array
    {
        return $this->cachear('portal:puntos:v2:'.$this->revisionDatos().':'.sha1(json_encode($filtros)), function () use ($filtros): array {
            $taxones = DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'rango', 'nombre_cientifico'])->keyBy('id');
            return $this->agruparPuntos($this->consulta($filtros), $taxones);
        });
    }

    private function revisionDatos(): int
    {
        return (int) DB::table('divulgacion.portal_cache_revision')->where('id', 1)->value('version');
    }

    /** Un punto mantiene la latitud y longitud almacenadas; solo agrupa coincidencias exactas. */
    private function agruparPuntos(Builder $base, \Illuminate\Support\Collection $taxones): array
    {
        $filas = (clone $base)->where('ed.decimal_latitude_visible', true)->where('ed.decimal_longitude_visible', true)
            ->whereBetween('te.decimal_latitude', [-90, 90])->whereBetween('te.decimal_longitude', [-180, 180])
            ->selectRaw('te.decimal_latitude AS lat, te.decimal_longitude AS lon, te.taxon_id, ed.scientific_name_visible, COUNT(*) AS total')
            ->groupBy('te.decimal_latitude', 'te.decimal_longitude', 'te.taxon_id', 'ed.scientific_name_visible')->cursor();
        $puntos = $filosPorTaxon = [];
        $idsValidos = array_fill_keys(CalidadDatoPublico::taxonesConLinajeValido($taxones), true);
        foreach ($filas as $fila) {
            $id = $fila->scientific_name_visible ? $fila->taxon_id : null;
            $filo = 'Sin filo';
            if ($id && isset($filosPorTaxon[$id])) $filo = $filosPorTaxon[$id];
            else {
                $origen = $id;
                $filo = $this->linajePublicoParaMosaico($id, $taxones, false, false)['phylum'] ?? 'Sin filo';
                if ($origen) $filosPorTaxon[$origen] = $filo;
            }
            $clave = $fila->lat.':'.$fila->lon;
            $puntos[$clave] ??= ['lat' => (float) $fila->lat, 'lon' => (float) $fila->lon, 'total' => 0, 'filos' => [], 'taxones' => []];
            $puntos[$clave]['total'] += (int) $fila->total;
            $puntos[$clave]['filos'][$filo] = ($puntos[$clave]['filos'][$filo] ?? 0) + (int) $fila->total;
            if ($fila->scientific_name_visible && isset($idsValidos[$fila->taxon_id])) $puntos[$clave]['taxones'][$fila->taxon_id] = true;
        }
        foreach ($puntos as &$punto) $punto['taxones'] = count($punto['taxones']);
        unset($punto);
        uasort($puntos, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        return array_values($puntos);
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
            ->join('taxonomia.taxones as t', 't.id', '=', 'te.taxon_id')
            ->where('t.rango', 'especie')->whereRaw(CalidadDatoPublico::textoValido('t.nombre_cientifico'))->where('ed.scientific_name_visible', true)
            ->whereIn('t.id', CalidadDatoPublico::taxonesConLinajeValido(DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'nombre_cientifico'])))
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
        $datos = [];
        foreach (['codigo' => 'filtroCatalogo', 'preparaciones' => 'filtroPreparaciones', 'taxon' => 'filtroTaxon', 'pais' => 'filtroPais', 'provincia' => 'filtroProvincia', 'geografias' => 'filtroGeografias', 'filo' => 'filtroFiloId', 'mes' => 'filtroMes', 'identificacion' => 'filtroIdentificacion', 'ubicacion' => 'filtroSoloUbicacion', 'colector' => 'filtroColector', 'metodos' => 'filtroMetodos', 'lat_min' => 'filtroLatMin', 'lat_max' => 'filtroLatMax', 'lon_min' => 'filtroLonMin', 'lon_max' => 'filtroLonMax', 'elev_desde' => 'filtroElevDesde', 'elev_hasta' => 'filtroElevHasta', 'biomas' => 'filtroBiomas', 'habitat' => 'filtroHabitat', 'tipo' => 'filtroTipo', 'casta' => 'filtroCasta', 'estadio' => 'filtroEstadio'] as $clave => $propiedad) {
            if (isset($filtros[$clave])) $datos[$propiedad] = $filtros[$clave];
        }
        $datos['filtroFechaDesde'] = $filtros['desde_fecha'] ?? (isset($filtros['desde']) ? $filtros['desde'].'-01-01' : '');
        $datos['filtroFechaHasta'] = $filtros['hasta_fecha'] ?? (isset($filtros['hasta']) ? $filtros['hasta'].'-12-31' : '');
        $datos['filtroDatosCompletos'] = ($filtros['aptitud'] ?? '') === 'completos' ? '1' : '';
        if (isset($filtros['metodo'])) $datos['filtroMetodos'] = [$filtros['metodo']];

        return app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica(
            FiltrosBusqueda::desde($datos), $filtros['nivel'] ?? '', $filtros['taxon_navegado'] ?? '',
        );
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
            ->where('d.publicado', true)->where('e.coordenadas_otras_regiones', false)->where('d.state_province_visible', true)
            ->whereRaw(NormalizacionGeografica::sql('e.state_province').' = ?', [NormalizacionGeografica::normalizar($filtros['provincia'])])->exists()) {
            unset($filtros['provincia']);
        }
        if (isset($filtros['filo']) && ! DB::table('taxonomia.taxones')->where('id', $filtros['filo'])->where('rango', 'phylum')->exists()) {
            unset($filtros['filo']);
        }
        if (isset($filtros['metodo']) && ! DB::table('taxonomia.especimenes as e')
            ->leftJoin('taxonomia.muestras_colecta as m', 'm.id', '=', 'e.muestra_id')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->where('e.coordenadas_otras_regiones', false)->where('d.sampling_protocol_visible', true)
            ->whereRaw('lower('.ProtocoloColectaPublico::sql('e', 'm').') = lower(?)', [$filtros['metodo']])->exists()) {
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
        $taxones = DB::table('taxonomia.taxones')->select('id', 'padre_id', 'rango', 'nombre_cientifico')->get()->keyBy('id');
        $idsValidos = CalidadDatoPublico::taxonesConLinajeValido($taxones);
        $especieValida = CalidadDatoPublico::textoValido('t.nombre_cientifico').' AND t.id = ANY(?::uuid[])';
        $idsPg = '{'.implode(',', $idsValidos).'}';
        $fechaValida = CalidadDatoPublico::fechaValida('te.fecha_colecta');
        $resumen = (array) (clone $base)->leftJoin('taxonomia.taxones as t', 't.id', '=', 'te.taxon_id')
            ->selectRaw("COUNT(*) AS registros, COUNT(*) FILTER (WHERE t.rango = 'especie' AND {$especieValida} AND ed.scientific_name_visible) AS identificados, COUNT(*) FILTER (WHERE {$fechaValida} AND ed.event_date_visible) AS fechados, COUNT(*) FILTER (WHERE te.decimal_latitude BETWEEN -90 AND 90 AND te.decimal_longitude BETWEEN -180 AND 180 AND ed.decimal_latitude_visible AND ed.decimal_longitude_visible) AS georreferenciados, COUNT(*) FILTER (WHERE t.rango = 'especie' AND {$especieValida} AND ed.scientific_name_visible AND {$fechaValida} AND ed.event_date_visible AND te.decimal_latitude BETWEEN -90 AND 90 AND te.decimal_longitude BETWEEN -180 AND 180 AND ed.decimal_latitude_visible AND ed.decimal_longitude_visible) AS aptos", [$idsPg, $idsPg])
            ->first();

        $porTaxon = (clone $base)->where('ed.scientific_name_visible', true)->selectRaw('te.taxon_id, ed.family_visible, ed.genus_visible, COUNT(*) AS total')
            ->groupBy('te.taxon_id', 'ed.family_visible', 'ed.genus_visible')->get();
        $filos = [];
        foreach ($porTaxon as $fila) {
            $filo = $this->linajePublicoParaMosaico($fila->taxon_id, $taxones, (bool) $fila->family_visible, (bool) $fila->genus_visible)['phylum'] ?? 'Sin filo';
            $filos[$filo] = ($filos[$filo] ?? 0) + (int) $fila->total;
        }
        unset($filos['Sin filo']);
        arsort($filos);

        $especies = (clone $base)->join('taxonomia.taxones as t', 't.id', '=', 'te.taxon_id')
            ->where('t.rango', 'especie')->whereRaw(CalidadDatoPublico::textoValido('t.nombre_cientifico'))->where('ed.scientific_name_visible', true)
            ->whereIn('t.id', $idsValidos)
            ->selectRaw('t.nombre_cientifico AS nombre, COUNT(*) AS total')
            ->groupBy('t.nombre_cientifico')->orderByDesc('total')->limit(20)->get()->map(static fn (object $fila): array => (array) $fila)->all();
        $riqueza = (clone $base)->join('taxonomia.taxones as riqueza_t', 'riqueza_t.id', '=', 'te.taxon_id')
            ->where('riqueza_t.rango', 'especie')->whereRaw(CalidadDatoPublico::textoValido('riqueza_t.nombre_cientifico'))->where('ed.scientific_name_visible', true)
            ->whereIn('riqueza_t.id', $idsValidos)
            ->whereRaw(CalidadDatoPublico::textoValido('te.state_province'))->where('ed.state_province_visible', true)->whereNotNull('te.state_province')->where('te.state_province', '<>', '')
            ->selectRaw('te.state_province AS provincia, COUNT(DISTINCT riqueza_t.nombre_cientifico) AS especies, COUNT(*) AS registros')
            ->groupBy('te.state_province')->orderByDesc('especies')->limit(10)->get()->map(static fn (object $fila): array => (array) $fila)->all();
        $decadas = (clone $base)->join('taxonomia.taxones as decada_t', 'decada_t.id', '=', 'te.taxon_id')
            ->where('decada_t.rango', 'especie')->whereRaw(CalidadDatoPublico::textoValido('decada_t.nombre_cientifico'))->where('ed.scientific_name_visible', true)
            ->whereIn('decada_t.id', $idsValidos)
            ->whereRaw(CalidadDatoPublico::fechaValida('te.fecha_colecta'))->where('ed.event_date_visible', true)->whereNotNull('te.fecha_colecta')
            ->selectRaw('FLOOR(EXTRACT(YEAR FROM te.fecha_colecta) / 10)::integer * 10 AS decada, COUNT(DISTINCT decada_t.nombre_cientifico) AS especies, COUNT(*) AS registros')
            ->groupByRaw('1')->orderBy('decada')->get()->map(static fn (object $fila): array => (array) $fila)->all();
        $raras = (clone $base)->join('taxonomia.taxones as rara_t', 'rara_t.id', '=', 'te.taxon_id')
            ->where('rara_t.rango', 'especie')->whereRaw(CalidadDatoPublico::textoValido('rara_t.nombre_cientifico'))->where('ed.scientific_name_visible', true)
            ->whereIn('rara_t.id', $idsValidos)
            ->selectRaw('rara_t.nombre_cientifico AS nombre, COUNT(*) AS total')
            ->groupBy('rara_t.nombre_cientifico')->havingRaw('COUNT(*) <= 3')
            ->orderBy('total')->orderBy('nombre')->limit(12)->get()->map(static fn (object $fila): array => (array) $fila)->all();

        $mapa = $this->agruparPuntos($base, $taxones);

        $estacionalidad = (clone $base)->where('ed.event_date_visible', true)
            ->whereRaw(CalidadDatoPublico::fechaValida('te.fecha_colecta'))
            ->selectRaw('EXTRACT(MONTH FROM te.fecha_colecta)::integer AS mes, COUNT(*) AS registros')
            ->groupByRaw('1')->orderBy('mes')->get()->map(static fn (object $fila): array => (array) $fila)->all();
        $altitud = $agregadosAltitud = $limitesAltitud = [];
        $rangosAltitud = [[-500, -1], [0, 499], [500, 999], [1000, 1499], [1500, 1999], [2000, 2499], [2500, 2999], [3000, 3999], [4000, 9000]];
        foreach ($rangosAltitud as $i => [$desde, $hasta]) {
            $agregadosAltitud[] = "COUNT(*) FILTER (WHERE COALESCE(te.elevation_max_m, te.elevation_min_m) >= ? AND COALESCE(te.elevation_min_m, te.elevation_max_m) <= ?) AS rango_{$i}";
            array_push($limitesAltitud, $desde, $hasta);
        }
        $conteosAltitud = (clone $base)->where('ed.elevation_visible', true)->selectRaw(implode(', ', $agregadosAltitud), $limitesAltitud)->first();
        foreach ($rangosAltitud as $i => [$desde, $hasta]) {
            $cantidad = (int) $conteosAltitud->{'rango_'.$i};
            if ($cantidad > 0) $altitud[] = ['desde' => $desde, 'hasta' => $hasta, 'registros' => $cantidad];
        }
        $protocolo = ProtocoloColectaPublico::sql('te', 'metodo_panel');
        $metodos = (clone $base)->leftJoin('taxonomia.muestras_colecta as metodo_panel', 'metodo_panel.id', '=', 'te.muestra_id')
            ->where('ed.sampling_protocol_visible', true)->whereRaw(CalidadDatoPublico::textoValido($protocolo))
            ->selectRaw($protocolo.' AS metodo, COUNT(*) AS registros')
            ->groupByRaw($protocolo)->orderByDesc('registros')->orderBy('metodo')->get()
            ->map(static fn (object $fila): array => (array) $fila)->all();
        $mosaico = (clone $base)->join('divulgacion.imagenes_taxonomicas as foto', 'foto.occurrence_id', '=', 'te.occurrence_id')
            ->leftJoin('taxonomia.taxones as foto_taxon', 'foto_taxon.id', '=', 'te.taxon_id')
            ->where('ed.scientific_name_visible', true)
            ->whereRaw('(SELECT COUNT(*) FROM taxonomia.especimenes identidad WHERE identidad.occurrence_id = te.occurrence_id) = 1')
            ->orderByDesc('foto.created_at')->orderBy('foto.id')->limit(4)
            ->get(['foto.ruta', 'foto.nombre_original', 'foto_taxon.nombre_cientifico as taxon', 'te.occurrence_id', 'ed.occurrence_id_visible'])
            ->map(static fn (object $fila): array => ['url' => StorageImagenesAdapter::urlPublica($fila->ruta),
                'nombre' => $fila->nombre_original, 'taxon' => $fila->taxon,
                'occurrence_id' => $fila->occurrence_id_visible ? $fila->occurrence_id : null])->all();

        return [
            'resumen' => $resumen,
            'filos' => $filos,
            'especies' => $especies,
            'riqueza' => $riqueza,
            'decadas' => $decadas,
            'raras' => $raras,
            'mapa' => $mapa,
            'estacionalidad' => $estacionalidad,
            'altitud' => $altitud,
            'metodos' => $metodos,
            'mosaico' => $mosaico,
            'taxon_mosaico' => $this->taxonParaMosaico($base, $taxones, $filtros),
            'ilustraciones_mosaico' => \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::mosaicoParaSeleccion(
                (function () use ($porTaxon, $taxones): \Generator {
                    foreach ($porTaxon as $fila) {
                        yield $this->linajePublicoParaMosaico($fila->taxon_id, $taxones, (bool) $fila->family_visible, (bool) $fila->genus_visible) + ['total' => (int) $fila->total];
                    }
                })(),
            ),
        ];
    }

    /** Linaje del taxón seleccionado, respaldado por un ejemplar de la selección pública. */
    private function taxonParaMosaico(Builder $base, \Illuminate\Support\Collection $taxones, array $filtros): array
    {
        $nombre = trim((string) ($filtros['taxon_navegado'] ?? '')) ?: trim((string) ($filtros['taxon'] ?? ''));
        if ($nombre !== '') {
            $candidatos = $taxones->filter(static fn (object $t): bool => mb_strtolower($t->nombre_cientifico) === mb_strtolower($nombre));
            if ($candidatos->count() !== 1) return [];
            $elegido = $candidatos->first();
        } elseif (! empty($filtros['filo']) && isset($taxones[$filtros['filo']])) {
            $elegido = $taxones[$filtros['filo']];
        } else {
            return [];
        }
        $consulta = (clone $base)->where('ed.scientific_name_visible', true)
            ->whereRaw('te.taxon_id IN (WITH RECURSIVE seleccion AS (SELECT id FROM taxonomia.taxones WHERE id = ? UNION SELECT t.id FROM taxonomia.taxones t JOIN seleccion s ON t.padre_id = s.id) SELECT id FROM seleccion)', [$elegido->id]);
        if ($elegido->rango === 'familia') $consulta->where('ed.family_visible', true);
        if ($elegido->rango === 'genero') $consulta->where('ed.genus_visible', true);
        $ejemplar = $consulta->orderByDesc('ed.family_visible')->orderByDesc('ed.genus_visible')->orderBy('te.id')
            ->first(['ed.family_visible', 'ed.genus_visible']);
        if ($ejemplar === null) return [];
        return $this->linajePublicoParaMosaico($elegido->id, $taxones, (bool) $ejemplar->family_visible, (bool) $ejemplar->genus_visible);
    }

    private function linajePublicoParaMosaico(?string $id, \Illuminate\Support\Collection $taxones, bool $familia, bool $genero): array
    {
        $rangos = ['reino' => 'kingdom', 'phylum' => 'phylum', 'clase' => 'class', 'orden' => 'order', 'familia' => 'family', 'genero' => 'genus', 'especie' => 'species'];
        $fuente = []; $visitados = [];
        while ($id && isset($taxones[$id]) && ! isset($visitados[$id]) && count($visitados) < 30) {
            $visitados[$id] = true; $nodo = $taxones[$id];
            array_unshift($fuente, ['rango' => $nodo->rango, 'nombre' => $nodo->nombre_cientifico]);
            $id = $nodo->padre_id;
        }
        if ($id && isset($taxones[$id])) return []; // Ciclo o cadena que excede treinta nodos.
        $linaje = [];
        foreach (CalidadDatoPublico::rutaConfirmada($fuente) as $nodo) {
            if (($nodo['rango'] === 'familia' && ! $familia) || ($nodo['rango'] === 'genero' && ! $genero)) continue;
            if (isset($rangos[$nodo['rango']])) $linaje[$rangos[$nodo['rango']]] = $nodo['nombre'];
            $linaje['ancestros'][] = $nodo;
            $linaje['nombre'] = $nodo['nombre'];
        }
        return $linaje;
    }
}
