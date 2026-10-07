<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Livewire\Attributes\Locked;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\ExploradorTaxonomicoPublico;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;

trait ExploraTaxonomia
{
    #[Locked]
    public string $filtroTaxonId = '';

    private function contextoExplorador(): FiltrosBusqueda
    {
        return FiltrosBusqueda::desde(array_replace($this->valoresFiltros(), ['filtroTaxonId' => '']));
    }

    public function consultarExploradorTaxonomico(?string $padre = null, string $busqueda = ''): array
    {
        $resultado = app(ExploradorTaxonomicoPublico::class)->consultar($this->contextoExplorador(), $padre, $busqueda);
        $resultado['total'] = app(EloquentProveedorEspecimenesParaArbol::class)
            ->consultaPublica($this->filtrosActuales(), $this->nivel, $this->taxon)->count('te.id');
        $resultado['seleccionado'] = $this->filtroTaxonId;
        $this->skipRender();
        return $resultado;
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
