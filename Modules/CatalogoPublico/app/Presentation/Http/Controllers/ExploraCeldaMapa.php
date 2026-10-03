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
    public int $paginaArbolCelda = 1;

    public function abrirCelda(float $latitud, float $longitud): void
    {
        if (! is_finite($latitud) || ! is_finite($longitud) || abs($latitud) > 90 || abs($longitud) > 180) return;
        $this->celdaMapa = ['lat' => $latitud, 'lon' => $longitud];
        $this->rutaCelda = [];
        $this->vistaCelda = 'grupos';
        $this->paginaCelda = 1;
        $this->paginaArbolCelda = 1;
        unset($this->detalleCelda);
        $this->dispatch('abrir-detalle-celda');
    }

    public function cerrarCelda(): void
    {
        $this->celdaMapa = null;
        $this->rutaCelda = [];
        $this->vistaCelda = 'grupos';
        $this->paginaCelda = 1;
        $this->paginaArbolCelda = 1;
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
        $ruta = $this->detalleCelda['rutas'][$id] ?? null;
        if ($ruta === null) return;
        $this->rutaCelda = $ruta;
        $this->paginaCelda = 1;
        unset($this->detalleCelda);
    }

    public function volverCelda(int $profundidad): void
    {
        $this->rutaCelda = array_slice($this->rutaCelda, 0, max(0, $profundidad));
        $this->paginaCelda = 1;
        if ($profundidad <= 0) $this->paginaArbolCelda = 1;
        unset($this->detalleCelda);
    }

    public function paginarCelda(int $pagina): void
    {
        $this->paginaCelda = max(1, min($pagina, $this->detalleCelda['ultima']));
        unset($this->detalleCelda);
    }

    public function paginarArbolCelda(int $pagina): void
    {
        $this->paginaArbolCelda = max(1, min($pagina, $this->detalleCelda['arbol_ultima']));
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
        // Releer la ruta vigente también retira nombres cuyos permisos cambiaron
        // mientras el diálogo estaba abierto.
        $idRuta = $this->rutaCelda === [] ? null : end($this->rutaCelda)['id'];
        $this->rutaCelda = $idRuta ? ($rutasDisponibles[$idRuta] ?? []) : [];
        $seleccion = array_column($this->rutaCelda, 'id');
        $grupos = $taxonesPorVisibilidad = [];
        $curatoriales = [];
        $curatoriales_total = 0;
        $directos = $total = 0;
        foreach ($filasConRuta as $entrada) {
            $fila = $entrada['fila'];
            $ruta = $entrada['ruta'];
            if (array_slice(array_column($ruta, 'id'), 0, count($seleccion)) !== $seleccion) continue;
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
        if ($seleccion !== []) {
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
        $mostrarRegistros = $this->vistaCelda === 'registros' || $grupos === [];
        $ultima = max(1, (int) ceil(($mostrarRegistros ? $total : $totalGrupos) / 12));
        $pagina = min($this->paginaCelda, $ultima);
        $grupos = array_slice($grupos, $mostrarRegistros ? 0 : ($pagina - 1) * 12, 12, true);
        $registros = $imagenes = [];
        if ($mostrarRegistros) {
            $ids = (clone $base)->orderBy('te.fila_origen_excel')->orderBy('te.id')->offset(($pagina - 1) * 12)->limit(12)->pluck('te.id')->all();
            $registros = $this->cargarDetallesPorEspecimenIds($ids, app(ProveedorEspecimenesPort::class), app(EspecimenDivulgableRepositoryInterface::class));
            $imagenes = $this->cargarImagenesPorEspecimen(array_values(array_filter(array_column($registros, 'occurrence_id'))));
        }
        // Vista de conjunto desde abrir el punto: como máximo doce hojas reales
        // y sus ancestros publicados, reutilizando los agregados ya obtenidos.
        $padres = array_fill_keys(array_filter(array_column($nodos, 'padre_id')), true);
        $hojas = array_diff_key($nodos, $padres);
        uasort($hojas, static fn (array $a, array $b): int => strnatcasecmp($a['nombre'], $b['nombre']) ?: strcmp($a['id'], $b['id']));
        $arbol_hojas_total = count($hojas);
        $arbol_ultima = max(1, (int) ceil($arbol_hojas_total / 12));
        $arbol_pagina = min(max(1, $this->paginaArbolCelda), $arbol_ultima);
        $arbol_registros_total = (int) $filas->sum('total');
        $arbol = [];
        foreach (array_slice($hojas, ($arbol_pagina - 1) * 12, 12, true) as $id => $hoja) {
            foreach ($rutasDisponibles[$id] as $nodo) $arbol[$nodo['id']] = $nodos[$nodo['id']];
        }
        $idSeleccionado = $seleccion === [] ? null : end($seleccion);
        $seleccionado = $idSeleccionado ? ($nodos[$idSeleccionado] ?? null) : null;
        $rutas = array_intersect_key($rutasDisponibles, $arbol);
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
        return compact('total', 'grupos', 'totalGrupos', 'directos', 'registros', 'imagenes', 'pagina', 'ultima', 'arbol', 'rutas',
            'arbol_hojas_total', 'arbol_pagina', 'arbol_ultima', 'arbol_registros_total', 'seleccionado', 'informacion',
            'curatoriales', 'curatoriales_total');
    }
}
