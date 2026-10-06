<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;

trait ExploraCeldaMapa
{
    #[Locked]
    public ?array $celdaMapa = null;

    #[Locked]
    public array $rutaCelda = [];

    #[Locked]
    public string $vistaCelda = 'grupos';

    #[Locked]
    public int $paginaCelda = 1;

    #[Locked]
    public int $registrosPorPaginaCelda = 6;

    #[Locked]
    public ?string $registroCeldaId = null;

    public string $busquedaRegistroCelda = '';

    public function seleccionarRegistroCelda(string $id): void
    {
        if (!\Illuminate\Support\Str::isUuid($id) || !$this->consultaCelda()->where('te.id', $id)->exists()) abort(404);
        $this->registroCeldaId = $id;
        $this->rutaCelda = [];
        $this->paginaCelda = 1;
        unset($this->detalleCelda);
        $this->dispatch('registro-celda-seleccionado');
    }

    public function abrirCelda(float $latitud, float $longitud): void
    {
        if (! is_finite($latitud) || ! is_finite($longitud) || abs($latitud) > 90 || abs($longitud) > 180) return;
        $this->celdaMapa = ['lat' => $latitud, 'lon' => $longitud];
        $this->rutaCelda = [];
        $this->vistaCelda = 'grupos';
        $this->paginaCelda = 1;
        $this->registroCeldaId = null;
        $this->busquedaRegistroCelda = '';
        unset($this->detalleCelda);
        $this->dispatch('abrir-detalle-celda');
    }

    public function cerrarCelda(): void
    {
        $this->celdaMapa = null;
        $this->rutaCelda = [];
        $this->vistaCelda = 'grupos';
        $this->paginaCelda = 1;
        $this->registroCeldaId = null;
        unset($this->detalleCelda);
    }

    public function cambiarVistaCelda(string $vista): void
    {
        if (! in_array($vista, ['grupos', 'registros'], true)) return;
        $this->vistaCelda = $vista;
        $this->paginaCelda = 1;
        unset($this->detalleCelda);
    }

    /** Solo permite navegar a las ramas públicas del árbol visible. */
    public function navegarCelda(string $id): void
    {
        if (str_starts_with($id, 'registro:')) {
            $registro = $this->detalleCelda['registros_arbol'][$id] ?? null;
            if ($registro === null) return;
            $this->registroCeldaId = $registro['especimen_id'];
            $this->rutaCelda = $this->detalleCelda['rutas'][$registro['padre_id'] ?? ''] ?? [];
            $this->paginaCelda = 1;
            unset($this->detalleCelda);
            return;
        }
        $ruta = $this->detalleCelda['rutas'][$id] ?? null;
        if ($ruta === null) return;
        $this->registroCeldaId = null;
        $this->rutaCelda = $ruta;
        $this->paginaCelda = 1;
        unset($this->detalleCelda);
    }

    public function volverCelda(int $profundidad): void
    {
        $this->rutaCelda = array_slice($this->rutaCelda, 0, max(0, $profundidad));
        $this->paginaCelda = 1;
        $this->registroCeldaId = null;
        unset($this->detalleCelda);
    }

    public function paginarCelda(int $pagina): void
    {
        if ($this->vistaCelda !== 'registros') return;
        $this->paginaCelda = max(1, min($pagina, $this->detalleCelda['ultima']));
        unset($this->detalleCelda);
    }

    private function consultaCelda(): Builder
    {
        $consulta = app(EloquentProveedorEspecimenesParaArbol::class)
            ->consultaPublica($this->filtrosActuales(), $this->nivel, $this->taxon)
            ->where('ed.decimal_latitude_visible', true)->where('ed.decimal_longitude_visible', true);
        if ($this->celdaMapa === null) return $consulta->whereRaw('1 = 0');
        return $consulta->where('te.decimal_latitude', $this->celdaMapa['lat'])
            ->where('te.decimal_longitude', $this->celdaMapa['lon']);
    }

    #[Computed]
    public function detalleCelda(): array
    {
        $base = $this->consultaCelda();
        $totalUbicacion = (clone $base)->count('te.id');
        $arbolResumido = $totalUbicacion > 5;
        $buscar = mb_substr(trim($this->busquedaRegistroCelda), 0, 120);
        $consultaLov = clone $base;
        if ($buscar !== '') {
            $patron = '%'.addcslashes($buscar, '\\%_').'%';
            $consultaLov->leftJoin('taxonomia.taxones as tlov', 'tlov.id', '=', 'te.taxon_id')->where(function (Builder $q) use ($patron): void {
                $q->where(fn (Builder $codigo) => $codigo->where('ed.occurrence_id_visible', true)
                    ->where(fn (Builder $valores) => $valores->where('te.occurrence_id', 'ILIKE', $patron)->orWhere('te.codigo_catalogo', 'ILIKE', $patron)))
                    ->orWhere(fn (Builder $nombre) => $nombre->where('ed.scientific_name_visible', true)->where('tlov.nombre_cientifico', 'ILIKE', $patron));
            });
        } else {
            $consultaLov->leftJoin('taxonomia.taxones as tlov', 'tlov.id', '=', 'te.taxon_id');
        }
        $registrosLov = $consultaLov->select('te.id')->selectRaw('CASE WHEN ed.occurrence_id_visible THEN te.occurrence_id END AS codigo, CASE WHEN ed.scientific_name_visible THEN tlov.nombre_cientifico END AS nombre')
            ->orderBy('te.fila_origen_excel')->orderBy('te.id')->limit(31)->get()->map(static fn (object $fila): array => (array) $fila)->all();
        $lovHayMas = count($registrosLov) > 30;
        $registrosLov = array_slice($registrosLov, 0, 30);
        $taxones = DB::table('taxonomia.taxones')->get(['id', 'padre_id', 'nombre_cientifico', 'rango', 'autor', 'anio_descripcion'])->keyBy('id');
        $idsValidos = array_fill_keys(CalidadDatoPublico::taxonesConLinajeValido($taxones), true);
        $filas = (clone $base)->selectRaw('te.taxon_id, ed.scientific_name_visible, ed.family_visible, ed.genus_visible, COUNT(*) AS total')
            ->groupBy('te.taxon_id', 'ed.scientific_name_visible', 'ed.family_visible', 'ed.genus_visible')->get();
        $filasConRuta = $variantes = [];
        foreach ($filas as $fila) {
            $fuente = [];
            $id = $fila->scientific_name_visible ? $fila->taxon_id : null;
            $visitados = [];
            while ($id && isset($taxones[$id]) && ! isset($visitados[$id]) && count($visitados) < 30) {
                $visitados[$id] = true;
                $t = $taxones[$id];
                array_unshift($fuente, ['taxon_id' => $id, 'nombre' => $t->nombre_cientifico, 'rango' => $t->rango]);
                $id = $t->padre_id;
            }
            $confirmada = $id && isset($taxones[$id]) ? [] : CalidadDatoPublico::rutaConfirmada($fuente);
            if ($confirmada !== [] && ! isset($idsValidos[$confirmada[0]['taxon_id']])) $confirmada = [];
            $ruta = array_values(array_filter($confirmada, static fn (array $n): bool =>
                ($n['rango'] !== 'familia' || $fila->family_visible) && ($n['rango'] !== 'genero' || $fila->genus_visible)));
            $prefijo = [];
            foreach ($ruta as &$nodo) {
                $prefijo[] = $nodo['taxon_id'];
                $nodo['firma_ruta'] = sha1(implode(':', $prefijo));
                $variantes[$nodo['taxon_id']][$nodo['firma_ruta']] = true;
            }
            unset($nodo);
            $filasConRuta[] = ['fila' => $fila, 'ruta' => $ruta,
                'curatorial' => $fuente !== [] && ! isset($idsValidos[$fila->taxon_id])];
        }
        $nodos = $rutasDisponibles = [];
        foreach ($filasConRuta as &$entrada) {
            foreach ($entrada['ruta'] as &$nodo) {
                // Un mismo taxón puede tener distintos padres públicos según los
                // permisos del ejemplar. Su UUID se conserva separado de la rama.
                $nodo['id'] = count($variantes[$nodo['taxon_id']]) === 1
                    ? $nodo['taxon_id'] : $nodo['taxon_id'].':'.$nodo['firma_ruta'];
                unset($nodo['firma_ruta']);
            }
            unset($nodo);
            $ruta = $entrada['ruta'];
            foreach ($ruta as $i => $nodo) {
                $idNodo = $nodo['id'];
                $nodos[$idNodo] ??= $nodo + ['padre_id' => $ruta[$i - 1]['id'] ?? null, 'total' => 0];
                $nodos[$idNodo]['total'] += (int) $entrada['fila']->total;
                $rutasDisponibles[$idNodo] = array_slice($ruta, 0, $i + 1);
            }
        }
        unset($entrada);
        // Cada registro termina en una hoja propia, incluso si su identificación llega solo a familia.
        $rutasPorPermiso = [];
        foreach ($filasConRuta as $entrada) {
            $fila = $entrada['fila'];
            $clave = $fila->taxon_id.':'.(int) $fila->scientific_name_visible.':'.(int) $fila->family_visible.':'.(int) $fila->genus_visible;
            $rutasPorPermiso[$clave] = $entrada['ruta'];
        }
        $registros_arbol = [];
        $hojas = clone $base;
        foreach ($hojas->select('te.id', 'te.taxon_id', 'ed.scientific_name_visible', 'ed.family_visible', 'ed.genus_visible')
            ->selectRaw('CASE WHEN ed.occurrence_id_visible THEN te.occurrence_id END AS codigo_publico')
            ->orderBy('te.fila_origen_excel')->orderBy('te.id')->get() as $indice => $fila) {
            $clave = $fila->taxon_id.':'.(int) $fila->scientific_name_visible.':'.(int) $fila->family_visible.':'.(int) $fila->genus_visible;
            $rutaRegistro = $rutasPorPermiso[$clave] ?? [];
            $padreRegistro = $rutaRegistro === [] ? null : $rutaRegistro[array_key_last($rutaRegistro)]['id'];
            $idRegistro = 'registro:'.$fila->id;
            $registros_arbol[$idRegistro] = ['id' => $idRegistro, 'taxon_id' => null, 'especimen_id' => $fila->id,
                'padre_id' => $padreRegistro, 'nombre' => trim((string) $fila->codigo_publico) ?: 'Registro público '.($indice + 1),
                'rango' => 'registro', 'total' => 1];
        }
        if ($this->registroCeldaId === null && $this->rutaCelda === [] && count($registros_arbol) === 1) $this->registroCeldaId = reset($registros_arbol)['especimen_id'];
        $registro_seleccionado = $registros_arbol['registro:'.$this->registroCeldaId] ?? null;
        if ($registro_seleccionado === null) $this->registroCeldaId = null;
        // Releer la ruta vigente también retira nombres cuyos permisos cambiaron
        // mientras el diálogo estaba abierto.
        $idRuta = $registro_seleccionado !== null ? $registro_seleccionado['padre_id']
            : ($this->rutaCelda === [] ? null : end($this->rutaCelda)['id']);
        $this->rutaCelda = $idRuta ? ($rutasDisponibles[$idRuta] ?? []) : [];
        $seleccion = array_column($this->rutaCelda, 'id');
        $grupos = $taxonesPorVisibilidad = [];
        $curatoriales = [];
        $curatoriales_total = 0;
        $directos = $total = 0;
        foreach ($filasConRuta as $entrada) {
            $fila = $entrada['fila'];
            $ruta = $entrada['ruta'];
            if ($this->vistaCelda !== 'registros' && array_slice(array_column($ruta, 'id'), 0, count($seleccion)) !== $seleccion) continue;
            $total += (int) $fila->total;
            if ($entrada['curatorial']) {
                $clave = $fila->taxon_id.':'.implode(':', array_column($ruta, 'id'));
                $curatoriales[$clave] ??= ['id' => $fila->taxon_id, 'nota' => $taxones[$fila->taxon_id]->nombre_cientifico,
                    'padre' => $ruta === [] ? 'root' : end($ruta)['nombre'], 'ruta' => $ruta, 'total' => 0];
                $curatoriales[$clave]['total'] += (int) $fila->total;
                $curatoriales_total += (int) $fila->total;
            }
            if ($fila->scientific_name_visible) {
                $permisos = (int) $fila->family_visible.':'.(int) $fila->genus_visible;
                $taxonesPorVisibilidad[$permisos][] = $fila->taxon_id;
            }
            $hijo = $ruta[count($seleccion)] ?? null;
            if ($hijo === null) { $directos += (int) $fila->total; continue; }
            $grupos[$hijo['id']] ??= $hijo + ['total' => 0];
            $grupos[$hijo['id']]['total'] += (int) $fila->total;
        }
        if ($seleccion !== [] && $this->vistaCelda !== 'registros') {
            // El conteo y los UUID paginados usan los mismos grupos de permisos,
            // incluso cuando un linaje omite familia o género por privacidad.
            $base->where(function (Builder $query) use ($taxonesPorVisibilidad): void {
                $query->whereRaw('1 = 0');
                foreach ($taxonesPorVisibilidad as $permisos => $ids) {
                    [$familia, $genero] = explode(':', $permisos);
                    $query->orWhere(fn (Builder $q) => $q->where('ed.scientific_name_visible', true)
                        ->where('ed.family_visible', (bool) $familia)->where('ed.genus_visible', (bool) $genero)
                        ->whereIn('te.taxon_id', array_unique($ids)));
                }
            });
        }
        uasort($grupos, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']));
        $totalGrupos = count($grupos);
        $mostrarRegistros = $this->vistaCelda === 'registros' || $grupos === [] || $registro_seleccionado !== null;
        $tamanoPagina = $this->vistaCelda === 'registros' ? $this->registrosPorPaginaCelda : EloquentProveedorEspecimenesParaArbol::TAMANO_PAGINA;
        $ultima = max(1, (int) ceil(($mostrarRegistros ? $total : $totalGrupos) / $tamanoPagina));
        $pagina = min($this->paginaCelda, $ultima);
        $grupos = array_slice($grupos, $mostrarRegistros ? 0 : ($pagina - 1) * $tamanoPagina, $tamanoPagina, true);
        $registros = $imagenes = [];
        if ($mostrarRegistros) {
            $consultaRegistros = clone $base;
            if ($this->vistaCelda === 'grupos' && $registro_seleccionado !== null) $consultaRegistros->where('te.id', $registro_seleccionado['especimen_id']);
            $ids = $consultaRegistros->orderBy('te.fila_origen_excel')->orderBy('te.id')->offset(($pagina - 1) * $tamanoPagina)->limit($tamanoPagina)->pluck('te.id')->all();
            $registros = $this->cargarDetallesPorEspecimenIds($ids, app(ProveedorEspecimenesPort::class), app(EspecimenDivulgableRepositoryInterface::class));
            $imagenes = $this->cargarImagenesPorEspecimen(array_values(array_filter(array_column($registros, 'occurrence_id'))));
        }
        $arbol_hojas_total = $totalUbicacion;
        $arbol_registros_total = (int) $filas->sum('total');
        $arbol = $nodos + $registros_arbol;
        // El índice completo conserva rutas y permisos para la búsqueda; solo la presentación se acota.
        $arbolVisual = $arbolResumido ? array_filter($nodos, static fn (array $n): bool => in_array(mb_strtolower($n['rango']), ['reino', 'kingdom', 'subreino', 'phylum', 'filo', 'subfilo', 'clase', 'class', 'subclase', 'orden', 'order', 'suborden', 'infraorden', 'superfamilia', 'familia', 'family'], true)) : $arbol;
        $arbolVisual = array_values($arbolVisual);
        $idSeleccionado = $seleccion === [] ? null : end($seleccion);
        $seleccionado = $idSeleccionado ? ($nodos[$idSeleccionado] ?? null) : null;
        $rutas = $rutasDisponibles;
        $informacion = null;
        if ($seleccionado) {
            $idTaxonSeleccionado = $seleccionado['taxon_id'];
            $rangos = ['reino' => 'kingdom', 'subreino' => 'subkingdom', 'filo' => 'phylum', 'subfilo' => 'subphylum',
                'clase' => 'class', 'subclase' => 'subclass', 'orden' => 'order', 'suborden' => 'suborder', 'infraorden' => 'infraorder',
                'superfamilia' => 'superfamily', 'familia' => 'family', 'subfamilia' => 'subfamily', 'tribu' => 'tribe', 'subtribu' => 'subtribe',
                'genero' => 'genus', 'género' => 'genus', 'subgenero' => 'subgenus', 'subgénero' => 'subgenus',
                'especie' => 'species', 'subespecie' => 'subspecies', 'variedad' => 'variety'];
            $jerarquia = [];
            $ancestros = [];
            foreach ($this->rutaCelda as $nodo) {
                $rangoFuente = mb_strtolower(trim($nodo['rango']));
                $rango = $rangos[$rangoFuente] ?? $rangoFuente;
                $jerarquia[$rango] = $nodo['nombre'];
                $ancestros[] = ['rango' => $rango, 'nombre' => $nodo['nombre']];
            }
            // La lista conserva también rangos repetidos/desconocidos sin inventarlos.
            // Solo procede de la ruta pública seleccionada, incluyendo sus omisiones.
            $jerarquia['ancestros'] = $ancestros;
            $seleccionado['jerarquia'] = $jerarquia;
            $seleccionado['ilustracion'] = IlustracionTaxonomica::paraTaxon($jerarquia + ['nombre' => $seleccionado['nombre'], 'ancestros' => $this->rutaCelda]);
            $informacion = ['taxon' => $seleccionado['nombre'], 'rango' => $seleccionado['rango'], 'cantidad' => $total,
                'jerarquia' => $jerarquia, 'autor' => $taxones[$idTaxonSeleccionado]->autor,
                'anio_descripcion' => $taxones[$idTaxonSeleccionado]->anio_descripcion];
        }
        $arbol = array_values($arbol);
        uasort($curatoriales, static fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strnatcasecmp($a['nota'], $b['nota']));
        $curatoriales = array_values(array_slice($curatoriales, 0, 12));
        return compact('total', 'grupos', 'totalGrupos', 'directos', 'mostrarRegistros', 'registros', 'imagenes', 'pagina', 'ultima', 'arbol', 'rutas',
            'arbol_hojas_total', 'arbol_registros_total', 'seleccionado', 'informacion', 'registros_arbol', 'registro_seleccionado',
            'curatoriales', 'curatoriales_total', 'arbolResumido', 'arbolVisual', 'totalUbicacion', 'registrosLov', 'lovHayMas');
    }
}
