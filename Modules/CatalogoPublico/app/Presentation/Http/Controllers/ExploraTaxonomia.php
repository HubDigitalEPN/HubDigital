<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Livewire\Attributes\Locked;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

trait ExploraTaxonomia
{
    #[Locked]
    public string $filtroTaxonId = '';
    #[Locked]
    public array $filtroTaxonIds = [];

    private function ajustarFilosAlTexto(): void
    {
        unset($this->arbolFiloPanel, $this->criteriosActivos);
        if ($this->filtroTaxon === '') return;
        $explorador = app(ExploradorTaxonomicoPublico::class);
        $filtros = FiltrosBusqueda::desde(['filtroTaxon' => $this->filtroTaxon]);
        $filos = array_column($explorador->consultar($filtros)['nodos'], 'id');
        if (! in_array($this->filtroFiloId, $filos, true)) $this->filtroFiloId = '';
        $this->filtroFilos = array_values(array_intersect($this->filtroFilos, $filos));
        if ($this->filtroTaxonId !== '' && $explorador->taxon($filtros, $this->filtroTaxonId) === null) $this->filtroTaxonId = '';
        $this->filtroTaxonIds = array_values(array_filter($this->filtroTaxonIds, fn ($id) => $explorador->taxon($filtros, $id) !== null));
    }

    #[Computed]
    public function arbolFiloPanel(): array
    {
        $explorador = app(ExploradorTaxonomicoPublico::class);
        $filtros = FiltrosBusqueda::desde(['filtroTaxon' => $this->filtroTaxon]);
        $seleccion = $this->filtroTaxonId !== '' ? $explorador->taxon($filtros, $this->filtroTaxonId) : null;
        $selecciones = array_values(array_filter(array_map(fn ($id) => $explorador->taxon($filtros, $id), $this->filtroTaxonIds)));
        if ($seleccion === null && $this->filtroTaxon !== '') {
            $exactos = $explorador->exacto($filtros, $this->filtroTaxon);
            if (count($exactos) === 1) $seleccion = $exactos[0];
        }
        if ($this->filtroTaxon !== '') {
            // Un texto confirmado muestra todos los descendientes públicos
            // del resultado; no sólo Filo/Clase ni una precarga de 300 nodos.
            $datos = $explorador->estructura($filtros);
            $datos['expandidos'] = array_column(array_filter($datos['nodos'], static fn (array $n): bool => $n['tieneHijos']), 'clave');
        } else {
            $datos = $explorador->consultar($filtros, conservar: array_values(array_unique([...array_column($selecciones, 'clave'), ...($seleccion ? [$seleccion['clave']] : [])])));
        }
        $datos['contexto'] = $this->filtroTaxon.':'.DB::table('divulgacion.portal_cache_revision')->where('id', 1)->value('version');
        $datos['busquedaTaxon'] = $this->filtroTaxon;
        $datos['filos'] = $this->filtrosActuales()->filosSeleccionados();
        $datos['seleccionado'] = $this->filtroTaxonId;
        $datos['seleccionados'] = $this->filtroTaxonIds;
        $datos['linaje'] = [];
        foreach ($selecciones as $seleccionActual) {
            $porClave = array_column($datos['nodos'], null, 'clave'); $cursor = $seleccionActual['clave']; $vistos = [];
            while ($cursor !== null && isset($porClave[$cursor]) && !isset($vistos[$cursor])) {
                $vistos[$cursor] = true; $datos['linaje'][] = $porClave[$cursor]['id'];
                $padre = $porClave[$cursor]['padre']; if ($padre !== null) $datos['expandidos'][] = $padre; $cursor = $padre;
            }
        }
        if ($seleccion) {
            $porClave = array_column($datos['nodos'], null, 'clave'); $cursor = $seleccion['clave'];
            $vistos = [];
            while ($cursor !== null && isset($porClave[$cursor]) && !isset($vistos[$cursor])) {
                $vistos[$cursor] = true; $datos['linaje'][] = $porClave[$cursor]['id'];
                $padre = $porClave[$cursor]['padre'];
                if ($padre !== null) $datos['expandidos'][] = $padre;
                $cursor = $padre;
            }
        }
        $datos['expandidos'] = array_values(array_unique($datos['expandidos']));
        return $datos;
    }

    public function seleccionarNodoFilo(string $id): array
    {
        $explorador = app(ExploradorTaxonomicoPublico::class);
        $filtros = FiltrosBusqueda::desde(['filtroTaxon' => $this->filtroTaxon]);
        $nodo = $explorador->taxon($filtros, $id);
        abort_if($nodo === null, 404);
        if ($nodo['padre'] === null) {
            if ($this->filtroTaxonId !== '' && in_array($id, $this->filtrosActuales()->filosSeleccionados(), true)
                && $explorador->taxon(FiltrosBusqueda::desde(['filtroTaxonId' => $this->filtroTaxonId]), $id)) $this->filtroTaxonId = '';
            $this->seleccionarFilo($id);
        } else {
            $seleccionados = array_values(array_unique([...$this->filtroTaxonIds, ...($this->filtroTaxonId !== '' ? [$this->filtroTaxonId] : [])]));
            $this->filtroTaxonIds = in_array($id, $seleccionados, true) ? array_values(array_diff($seleccionados, [$id])) : [...$seleccionados, $id];
            abort_if(count($this->filtroTaxonIds) > 100, 422, 'Selecciona hasta 100 taxones.');
            $this->filtroTaxonId = '';
            $this->nivel = $this->taxon = $this->explorar = '';
            $this->actualizarFiltros();
            $this->ajustarGeografiaDependiente();
            $this->borradorFiltros = $this->valoresBorrador();
            $this->mostrarFotoComposicion = false;
            $this->cerrarCelda(); $this->cerrarFichaRegistro();
        }
        unset($this->arbolFiloPanel, $this->criteriosActivos);
        return $this->arbolFiloPanel;
    }

    private function contextoExplorador(): FiltrosBusqueda
    {
        return FiltrosBusqueda::desde(array_replace($this->valoresFiltros(), ['filtroTaxonId' => '', 'filtroTaxonIds' => []]));
    }

    public function consultarExploradorTaxonomico(?string $padre = null, string $busqueda = '', array $conservar = []): array
    {
        $resultado = app(ExploradorTaxonomicoPublico::class)->consultar(FiltrosBusqueda::desde($this->valoresFiltros() + ['nivelNavegado' => $this->nivel, 'taxonNavegado' => $this->taxon]), $padre, $busqueda, $conservar);
        $resultado['total'] = $resultado['totalCatalogo'];
        $resultado['seleccionado'] = '';
        $this->skipRender();
        return $resultado;
    }

    public function verTaxonExploradorEnMapa(string $id): array
    {
        if (app(ExploradorTaxonomicoPublico::class)->taxon(FiltrosBusqueda::vacio(), $id) === null) {
            $this->skipRender();
            return ['error' => 'Este taxón ya no tiene registros públicos.'];
        }
        return $this->seleccionarTaxonExplorador($id);
    }

    public function seleccionarTaxonExplorador(string $id): array
    {
        $nodo = app(ExploradorTaxonomicoPublico::class)->taxon($this->contextoExplorador(), $id);
        if ($nodo === null) return ['error' => 'Este taxón ya no tiene registros públicos en los filtros actuales.'];
        // Livewire puede agrupar lectura y selección: el filtro debe renderizar aunque
        // otra acción del mismo request haya pedido omitir el render del árbol.
        $this->forceRender();
        \Livewire\store($this)->unset('skipRender');
        $this->filtroTaxonId = $nodo['id'];
        $this->filtroTaxonIds = [];
        $this->nivel = $this->taxon = $this->explorar = '';
        $this->vista = 'mapa';
        $this->mostrarFotoComposicion = false;
        $this->pagina = $this->paginaHermanos = 1;
        $this->cerrarCelda();
        $this->cerrarFichaRegistro();
        unset($this->geografiaDisponible, $this->provinciasDisponibles, $this->localidadesDisponibles, $this->criteriosActivos);
        $this->borradorFiltros = $this->valoresBorrador();
        $total = app(EloquentProveedorEspecimenesParaArbol::class)->consultaPublica($this->filtrosActuales())->count('te.id');
        return ['seleccionado' => $nodo['id'], 'nombre' => $nodo['nombre'], 'total' => $total, 'hoja' => $nodo['hoja']];
    }

    public function confirmarTaxonExplorador(string $nombre): array
    {
        $coincidencias = app(ExploradorTaxonomicoPublico::class)->exacto($this->contextoExplorador(), $nombre);
        $ids = array_values(array_unique(array_column($coincidencias, 'id')));
        if (count($ids) !== 1) {
            $this->skipRender();
            return ['error' => $ids === [] ? 'Escribe un nombre científico exacto o selecciona un resultado del árbol.'
                : 'Hay varios taxones con ese nombre. Selecciona su rama en el árbol.'];
        }
        return $this->seleccionarTaxonExplorador($ids[0]);
    }
}
