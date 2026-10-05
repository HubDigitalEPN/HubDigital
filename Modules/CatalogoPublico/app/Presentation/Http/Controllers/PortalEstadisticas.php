<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Modules\CatalogoPublico\Infrastructure\ElegibilidadGeograficaPortal;
use Modules\CatalogoPublico\Infrastructure\PresupuestoConsultaPortal;
use Modules\CatalogoPublico\Application\Services\ResumenEspeciesPublicas;
use Modules\CatalogoPublico\Application\Services\ResumenDistribucionPublica;

use Illuminate\Database\DeadlockException;
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
    private ?PresupuestoConsultaPortal $presupuesto = null;

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
            'fxprov' => $filtros['provincia_excluida'] ?? null,
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
    public function datosParaVista(array $filtros, bool $incluirContenidoTaxon = true): array
    {
        $firma = sha1(json_encode($filtros));
        $this->presupuesto = new PresupuestoConsultaPortal($firma);
        try {
            return $this->presupuesto->ejecutar(fn (): array => $this->cachear(
                'portal:estadisticas:v20:'.(int) $incluirContenidoTaxon.':'.$this->revisionDatos().':'.$firma,
                fn (): array => $this->resumir($filtros, $incluirContenidoTaxon),
            ));
        } finally {
            $this->presupuesto = null;
        }
    }

    private function medir(string $etapa, callable $calcular): mixed
    {
        return $this->presupuesto !== null ? $this->presupuesto->etapa($etapa, $calcular) : $calcular();
    }

    /** Distribución completa sin hidratar tarjetas ni calcular los otros indicadores. */
    public function puntosParaMapa(array $filtros): array
    {
        return $this->cachear('portal:puntos:v4:'.$this->revisionDatos().':'.sha1(json_encode($filtros)), function () use ($filtros): array {
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
        $idsValidos = CalidadDatoPublico::taxonesConLinajeValido(DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'nombre_cientifico']));
        $consulta = $this->consulta($filtros)
            ->join('taxonomia.taxones as t', 't.id', '=', 'te.taxon_id')
            ->where('t.rango', 'especie')->whereRaw(CalidadDatoPublico::textoValido('t.nombre_cientifico'))->where('ed.scientific_name_visible', true)
            ->whereRaw('t.id = ANY(?::uuid[])', ['{'.implode(',', $idsValidos).'}'])
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
        $datos['filtroProvinciaExcluida'] = $filtros['provincia_excluida'] ?? '';
        foreach (['codigo' => 'filtroCatalogo', 'preparaciones' => 'filtroPreparaciones', 'taxon' => 'filtroTaxon', 'pais' => 'filtroPais', 'provincia' => 'filtroProvincia', 'geografias' => 'filtroGeografias', 'filo' => 'filtroFiloId', 'mes' => 'filtroMes', 'identificacion' => 'filtroIdentificacion', 'ubicacion' => 'filtroSoloUbicacion', 'colector' => 'filtroColector', 'metodos' => 'filtroMetodos', 'lat_min' => 'filtroLatMin', 'lat_max' => 'filtroLatMax', 'lon_min' => 'filtroLonMin', 'lon_max' => 'filtroLonMax', 'elev_desde' => 'filtroElevDesde', 'elev_hasta' => 'filtroElevHasta', 'biomas' => 'filtroBiomas', 'habitat' => 'filtroHabitat', 'tipo' => 'filtroTipo', 'disposicion' => 'filtroDisposicion', 'casta' => 'filtroCasta', 'estadio' => 'filtroEstadio'] as $clave => $propiedad) {
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
            'provincia_excluida' => ['nullable', 'string', 'max:120'],
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
            ->where('d.publicado', true)->whereRaw(ElegibilidadGeograficaPortal::sql('e', 'd'))->where('d.state_province_visible', true)
            ->whereRaw(NormalizacionGeografica::sql('e.state_province').' = ?', [NormalizacionGeografica::normalizar($filtros['provincia'])])->exists()) {
            unset($filtros['provincia']);
        }
        if (isset($filtros['filo']) && ! DB::table('taxonomia.taxones')->where('id', $filtros['filo'])->where('rango', 'phylum')->exists()) {
            unset($filtros['filo']);
        }
        if (isset($filtros['metodo']) && ! DB::table('taxonomia.especimenes as e')
            ->leftJoin('taxonomia.muestras_colecta as m', 'm.id', '=', 'e.muestra_id')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->whereRaw(ElegibilidadGeograficaPortal::sql('e', 'd'))->where('d.sampling_protocol_visible', true)
            ->whereRaw(ProtocoloColectaPublico::claveSql('e', 'm').' = ?', [ProtocoloColectaPublico::clave($filtros['metodo'])])->exists()) {
            unset($filtros['metodo']);
        }
        if (isset($filtros['metodo'])) $filtros['metodo'] = ProtocoloColectaPublico::clave($filtros['metodo']);

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
            $guardado = $this->operacionCache(static fn () => Cache::get($clave));
            if (is_array($guardado)) {
                return $guardado;
            }
        } catch (DeadlockException $error) {
            // Laravel puede invalidar el savepoint tras un conflicto de
            // concurrencia: el presupuesto debe revertir la transacción exterior.
            throw $error;
        } catch (\Throwable $error) {
            Log::warning('Caché de estadísticas no disponible', ['operacion' => 'leer', 'tipo' => $error::class]);
        }

        $resultado = $calcular();
        try {
            $this->operacionCache(static fn () => Cache::put($clave, $resultado, 300));
        } catch (DeadlockException $error) {
            throw $error;
        } catch (\Throwable $error) {
            Log::warning('Caché de estadísticas no disponible', ['operacion' => 'guardar', 'tipo' => $error::class]);
        }

        return $resultado;
    }

    /** Un fallo SQL de caché revierte su savepoint antes de continuar el agregado. */
    private function operacionCache(callable $operacion): mixed
    {
        return DB::connection()->transactionLevel() > 0
            ? DB::transaction(static fn () => $operacion())
            : $operacion();
    }

    private function resumir(array $filtros, bool $incluirContenidoTaxon): array
    {
        $base = $this->consulta($filtros);
        $taxones = $this->medir('taxonomia', fn () => DB::table('taxonomia.taxones')->select('id', 'padre_id', 'rango', 'nombre_cientifico')->get()->keyBy('id'));
        $idsValidos = CalidadDatoPublico::taxonesConLinajeValido($taxones);
        $especieValida = CalidadDatoPublico::textoValido('t.nombre_cientifico').' AND t.id = ANY(?::uuid[])';
        $idsPg = '{'.implode(',', $idsValidos).'}';
        $fechaValida = CalidadDatoPublico::fechaValida('te.fecha_colecta');
        $resumen = (array) $this->medir('resumen', fn () => (clone $base)->leftJoin('taxonomia.taxones as t', 't.id', '=', 'te.taxon_id')
            ->selectRaw("COUNT(*) AS registros, COUNT(*) FILTER (WHERE t.rango = 'especie' AND {$especieValida} AND ed.scientific_name_visible) AS identificados, COUNT(*) FILTER (WHERE {$fechaValida} AND ed.event_date_visible) AS fechados, COUNT(*) FILTER (WHERE te.decimal_latitude BETWEEN -90 AND 90 AND te.decimal_longitude BETWEEN -180 AND 180 AND ed.decimal_latitude_visible AND ed.decimal_longitude_visible) AS georreferenciados, COUNT(*) FILTER (WHERE t.rango = 'especie' AND {$especieValida} AND ed.scientific_name_visible AND {$fechaValida} AND ed.event_date_visible AND te.decimal_latitude BETWEEN -90 AND 90 AND te.decimal_longitude BETWEEN -180 AND 180 AND ed.decimal_latitude_visible AND ed.decimal_longitude_visible) AS aptos", [$idsPg, $idsPg])
            ->first());

        $porTaxon = $this->medir('porTaxon', fn () => (clone $base)->where('ed.scientific_name_visible', true)->selectRaw('te.taxon_id, ed.family_visible, ed.genus_visible, COUNT(*) AS total')
            ->groupBy('te.taxon_id', 'ed.family_visible', 'ed.genus_visible')->get());
        $filos = [];
        foreach ($porTaxon as $fila) {
            $filo = $this->linajePublicoParaMosaico($fila->taxon_id, $taxones, (bool) $fila->family_visible, (bool) $fila->genus_visible)['phylum'] ?? 'Sin filo';
            $filos[$filo] = ($filos[$filo] ?? 0) + (int) $fila->total;
        }
        unset($filos['Sin filo']);
        arsort($filos);

        $conteosEspecies = $this->medir('especies', fn (): array => ResumenEspeciesPublicas::desde($porTaxon, $taxones, $idsValidos));
        $especies = $conteosEspecies['especies'];
        $provinciaClave = NormalizacionGeografica::sql('te.state_province');
        $riqueza = $this->medir('riqueza', fn (): array => ResumenDistribucionPublica::riqueza(
            (clone $base)->where('ed.scientific_name_visible', true)->where('ed.state_province_visible', true)
                ->whereRaw(CalidadDatoPublico::textoValido('te.state_province'))
                ->selectRaw("te.taxon_id, {$provinciaClave} AS provincia_clave, MIN(te.state_province) AS provincia, COUNT(*) AS registros")
                ->groupBy('te.taxon_id')->groupByRaw($provinciaClave)->get(),
            $taxones, $idsValidos,
        ));
        $decada = 'FLOOR(EXTRACT(YEAR FROM te.fecha_colecta) / 10)::integer * 10';
        $decadas = $this->medir('decadas', fn (): array => ResumenDistribucionPublica::decadas(
            (clone $base)->where('ed.scientific_name_visible', true)->where('ed.event_date_visible', true)
                ->whereRaw(CalidadDatoPublico::fechaValida('te.fecha_colecta'))
                ->selectRaw("te.taxon_id, {$decada} AS decada, COUNT(*) AS registros")
                ->groupBy('te.taxon_id')->groupByRaw($decada)->get(),
            $taxones, $idsValidos,
        ));
        $raras = $conteosEspecies['raras'];

        $mapa = $this->medir('puntos', fn () => $this->agruparPuntos($base, $taxones));

        $estacionalidad = $this->medir('estacionalidad', fn () => (clone $base)->where('ed.event_date_visible', true)
            ->whereRaw(CalidadDatoPublico::fechaValida('te.fecha_colecta'))
            ->selectRaw('EXTRACT(MONTH FROM te.fecha_colecta)::integer AS mes, COUNT(*) AS registros')
            ->groupByRaw('1')->orderBy('mes')->get()->map(static fn (object $fila): array => (array) $fila)->all());
        $altitud = $agregadosAltitud = $limitesAltitud = [];
        $rangosAltitud = [[-500, -1], [0, 499], [500, 999], [1000, 1499], [1500, 1999], [2000, 2499], [2500, 2999], [3000, 3999], [4000, 9000]];
        foreach ($rangosAltitud as $i => [$desde, $hasta]) {
            $agregadosAltitud[] = "COUNT(*) FILTER (WHERE COALESCE(te.elevation_max_m, te.elevation_min_m) >= ? AND COALESCE(te.elevation_min_m, te.elevation_max_m) <= ?) AS rango_{$i}";
            array_push($limitesAltitud, $desde, $hasta);
        }
        $conteosAltitud = $this->medir('conteosAltitud', fn () => (clone $base)->where('ed.elevation_visible', true)->selectRaw(implode(', ', $agregadosAltitud), $limitesAltitud)->first());
        foreach ($rangosAltitud as $i => [$desde, $hasta]) {
            $cantidad = (int) $conteosAltitud->{'rango_'.$i};
            if ($cantidad > 0) $altitud[] = ['desde' => $desde, 'hasta' => $hasta, 'registros' => $cantidad];
        }
        $protocoloFuente = ProtocoloColectaPublico::sql('te', 'metodo_panel');
        $protocolo = ProtocoloColectaPublico::claveSql('te', 'metodo_panel');
        $metodos = $this->medir('metodos', fn () => (clone $base)->leftJoin('taxonomia.muestras_colecta as metodo_panel', 'metodo_panel.id', '=', 'te.muestra_id')
            ->where('ed.sampling_protocol_visible', true)->whereRaw(CalidadDatoPublico::textoValido($protocolo))
            ->selectRaw($protocolo.' AS metodo, COUNT(*) AS registros, JSON_AGG(DISTINCT '.$protocoloFuente.' ORDER BY '.$protocoloFuente.') AS fuentes')
            ->groupByRaw($protocolo)->orderByDesc('registros')->orderBy('metodo')->get()
            ->map(static fn (object $fila): array => ['metodo' => $fila->metodo, 'registros' => (int) $fila->registros,
                'fuentes' => json_decode($fila->fuentes, true, flags: JSON_THROW_ON_ERROR)])->all());
        $mosaico = $taxonMosaico = $fotografiasMosaico = [];
        if ($incluirContenidoTaxon) {
            $mosaico = $this->medir('mosaico', fn () => (clone $base)->join('divulgacion.imagenes_taxonomicas as foto', 'foto.occurrence_id', '=', 'te.occurrence_id')
                ->leftJoin('taxonomia.taxones as foto_taxon', 'foto_taxon.id', '=', 'te.taxon_id')
                ->where('ed.scientific_name_visible', true)
                ->whereRaw('(SELECT COUNT(*) FROM taxonomia.especimenes identidad WHERE identidad.occurrence_id = te.occurrence_id) = 1')
                ->orderByDesc('foto.created_at')->orderBy('foto.id')->limit(4)
                ->get(['foto.ruta', 'foto.nombre_original', 'foto.autor_nombre_completo', 'foto_taxon.nombre_cientifico as taxon',
                    'te.taxon_id', 'te.country', 'ed.country_visible', 'ed.family_visible', 'ed.genus_visible', 'te.occurrence_id', 'ed.occurrence_id_visible'])
                ->map(function (object $fila) use ($taxones): array {
                    $linaje = $this->linajePublicoParaMosaico($fila->taxon_id, $taxones, (bool) $fila->family_visible, (bool) $fila->genus_visible);
                    $creditoEcuador = (bool) $fila->country_visible && mb_strtolower(trim((string) $fila->country)) === 'ecuador';
                    return ['url' => StorageImagenesAdapter::urlPublica($fila->ruta),
                        'nombre' => $fila->nombre_original, 'taxon' => $fila->taxon,
                        'family' => $linaje['family'] ?? null, 'genus' => $linaje['genus'] ?? null, 'species' => $linaje['species'] ?? null,
                        'autor' => $creditoEcuador ? $fila->autor_nombre_completo : null, 'credito_ecuador' => $creditoEcuador,
                        'occurrence_id' => $fila->occurrence_id_visible ? $fila->occurrence_id : null];
                })->all());

            $taxonMosaico = $this->taxonParaMosaico($porTaxon, $taxones, $filtros);
            $taxonSolicitado = trim((string) ($filtros['taxon_navegado'] ?? '')) ?: trim((string) ($filtros['taxon'] ?? ''));
            $linajesMosaico = (function () use ($porTaxon, $taxones): \Generator {
                foreach ($porTaxon as $fila) {
                    yield $this->linajePublicoParaMosaico($fila->taxon_id, $taxones, (bool) $fila->family_visible, (bool) $fila->genus_visible) + ['total' => (int) $fila->total];
                }
            })();
            $fotografiasMosaico = \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::mosaicoParaSeleccion(
                // Un nombre ambiguo o sin material público no se sustituye por fotos de otros grupos.
                $taxonSolicitado !== '' && $taxonMosaico === [] ? [] : $linajesMosaico,
                $taxonMosaico,
            );
        }

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
            'taxon_mosaico' => $taxonMosaico,
            'ilustraciones_mosaico' => $fotografiasMosaico,
            'descripcion_mosaico' => ! $incluirContenidoTaxon ? '' : ($mosaico !== []
                ? 'Fotografías publicadas de '.implode(', ', array_values(array_unique(array_filter(array_column($mosaico, 'taxon'))))).'. Las imágenes corresponden a ejemplares de la selección actual; su clasificación y sus datos públicos se consultan en el catálogo.'
                : \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::describirMosaico($fotografiasMosaico)),
        ];
    }

    /** Resuelve identidad y permisos dentro del agregado público ya seleccionado. */
    private function taxonParaMosaico(\Illuminate\Support\Collection $porTaxon, \Illuminate\Support\Collection $taxones, array $filtros): array
    {
        $navegado = trim((string) ($filtros['taxon_navegado'] ?? ''));
        $nombre = $navegado ?: trim((string) ($filtros['taxon'] ?? ''));
        $rangos = ['phylum' => 'phylum', 'class' => 'clase', 'order' => 'orden', 'family' => 'familia', 'genus' => 'genero', 'species' => 'especie'];
        $rango = $navegado !== '' ? ($rangos[$filtros['nivel'] ?? ''] ?? null) : null;
        $filoId = $nombre === '' ? ($filtros['filo'] ?? null) : null;
        if ($nombre === '' && $filoId === null) return [];

        $candidatos = [];
        foreach ($porTaxon as $fila) {
            $familia = (bool) $fila->family_visible;
            $genero = (bool) $fila->genus_visible;
            foreach ($this->rutaPublicaParaMosaico($fila->taxon_id, $taxones, $familia, $genero) as $nodo) {
                if ($nombre !== '') {
                    if (($rango !== null && $nodo['rango'] !== $rango)
                        || mb_strtolower($nodo['nombre']) !== mb_strtolower($nombre)) continue;
                } elseif ($nodo['id'] !== $filoId || $nodo['rango'] !== 'phylum') continue;

                // Variantes de permisos del mismo UUID no crean otro taxón. Dos UUID
                // compatibles dentro de la selección siguen siendo ambiguos.
                $permisos = ($familia ? 2 : 0) + ($genero ? 1 : 0);
                if (! isset($candidatos[$nodo['id']]) || $permisos > $candidatos[$nodo['id']]['permisos']) {
                    $candidatos[$nodo['id']] = ['permisos' => $permisos,
                        'linaje' => $this->linajePublicoParaMosaico($nodo['id'], $taxones, $familia, $genero)];
                }
                if (count($candidatos) > 1) return [];
            }
        }
        return $candidatos === [] ? [] : reset($candidatos)['linaje'];
    }

    /** Ruta real acotada, con el mismo prefijo confirmado y las mismas banderas del mosaico. */
    private function rutaPublicaParaMosaico(?string $id, \Illuminate\Support\Collection $taxones, bool $familia, bool $genero): array
    {
        $fuente = []; $visitados = [];
        while ($id && isset($taxones[$id]) && ! isset($visitados[$id]) && count($visitados) < 30) {
            $visitados[$id] = true; $nodo = $taxones[$id];
            array_unshift($fuente, ['id' => $id, 'rango' => $nodo->rango, 'nombre' => $nodo->nombre_cientifico]);
            $id = $nodo->padre_id;
        }
        if ($id && isset($taxones[$id])) return []; // Ciclo o cadena que excede treinta nodos.
        return array_values(array_filter(CalidadDatoPublico::rutaConfirmada($fuente), static fn (array $nodo): bool =>
            ($nodo['rango'] !== 'familia' || $familia) && ($nodo['rango'] !== 'genero' || $genero)));
    }

    private function linajePublicoParaMosaico(?string $id, \Illuminate\Support\Collection $taxones, bool $familia, bool $genero): array
    {
        $rangos = ['reino' => 'kingdom', 'phylum' => 'phylum', 'clase' => 'class', 'orden' => 'order', 'familia' => 'family', 'genero' => 'genus', 'especie' => 'species'];
        $linaje = [];
        foreach ($this->rutaPublicaParaMosaico($id, $taxones, $familia, $genero) as $nodo) {
            if (isset($rangos[$nodo['rango']])) $linaje[$rangos[$nodo['rango']]] = $nodo['nombre'];
            $linaje['ancestros'][] = ['rango' => $nodo['rango'], 'nombre' => $nodo['nombre']];
            $linaje['nombre'] = $nodo['nombre'];
        }
        return $linaje;
    }
}
