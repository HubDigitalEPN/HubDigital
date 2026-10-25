<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sección embebida en "Publicación GBIF": permite filtrar especímenes, marcar
 * con casillas cuáles exportar (la selección persiste entre páginas) y descargar
 * SOLO los seleccionados como Excel formateado. Es capa de presentación: delega
 * la búsqueda y la construcción de la tabla en sus handlers.
 */
final class ExportarEspecimenesGbif extends Component
{
    // ── Filtros ───────────────────────────────────────────────────────────────
    /** Nombre de taxón en cualquier nivel (reino, orden, familia, género, especie). */
    public string $fTaxon = '';

    public string $fLocalidad = '';

    public string $fColector = '';

    public string $fCatalogNumber = '';

    public string $fFechaDesde = '';

    public string $fFechaHasta = '';

    // ── Resultados / paginación ────────────────────────────────────────────────
    /** @var array<int, array<string, mixed>> */
    public array $especimenes = [];

    public bool $buscado = false;

    public int $page = 1;

    public int $perPage = 15;

    public int $total = 0;

    public int $totalPaginas = 1;

    // ── Selección (id => bool) ─────────────────────────────────────────────────
    /** @var array<string, bool> */
    public array $seleccionados = [];

    public ?string $mensaje = null;

    public ?string $error = null;

    public function buscar(BuscarEspecimenesHandler $handler): void
    {
        $this->page = 1;
        $this->ejecutarBusqueda($handler);
    }

    public function nextPage(BuscarEspecimenesHandler $handler): void
    {
        if ($this->page < $this->totalPaginas) {
            $this->page++;
            $this->ejecutarBusqueda($handler);
        }
    }

    public function irAPagina(int $pagina, BuscarEspecimenesHandler $handler): void
    {
        $this->page = max(1, min($this->totalPaginas, $pagina));
        $this->ejecutarBusqueda($handler);
    }

    public function prevPage(BuscarEspecimenesHandler $handler): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->ejecutarBusqueda($handler);
        }
    }

    private function ejecutarBusqueda(BuscarEspecimenesHandler $handler): void
    {
        $output = $handler->handle($this->construirInput($this->page, $this->perPage));

        $this->especimenes = $output->items;
        $this->total = $output->total;
        $this->totalPaginas = max(1, $output->totalPaginas);
        $this->buscado = true;
        $this->error = null;
    }

    /** Marca todos los especímenes que cumplen el filtro actual, también otras páginas. */
    public function seleccionarTodosFiltrados(BuscarEspecimenesHandler $handler): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        foreach (\App\Support\SeleccionExportacionCatalogo::ids($this->construirInput(1, 500)) as $id) $this->seleccionados[$id] = true;
        $this->mensaje = $this->contarSeleccionados().' especímenes seleccionados.';
        $this->error = null;
    }

    public function limpiarSeleccion(): void
    {
        $this->seleccionados = [];
        $this->mensaje = null;
    }

    public function contarSeleccionados(): int
    {
        return count(array_filter($this->seleccionados));
    }

    /** Descarga la selección como Excel formateado. */
    public function exportar(string $perfil = 'catalogo'): ?StreamedResponse
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        $ids = array_keys(array_filter($this->seleccionados));
        $filtrados = \App\Support\SeleccionExportacionCatalogo::ids($this->construirInput(1, 500));
        $ids = $ids === [] ? $filtrados : array_values(array_intersect($filtrados, $ids));
        $this->error = null;
        $this->mensaje = count($ids).' especímenes exportados. Revisa los campos obligatorios vacíos antes de publicar en IPT.';
        return app(\App\Support\ExportacionCatalogoExcel::class)->descargar($ids, $perfil);
    }

    private function construirInput(int $page, int $perPage): BuscarEspecimenesInput
    {
        return new BuscarEspecimenesInput(
            taxonNombre: $this->nullable($this->fTaxon),
            catalogNumber: $this->nullable($this->fCatalogNumber),
            localidad: $this->nullable($this->fLocalidad),
            colector: $this->nullable($this->fColector),
            fechaDesde: $this->nullable($this->fFechaDesde),
            fechaHasta: $this->nullable($this->fFechaHasta),
            page: $page,
            perPage: $perPage,
        );
    }

    private function nullable(string $valor): ?string
    {
        $v = trim($valor);

        return $v !== '' ? $v : null;
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.dataset.exportar-seleccion');
    }
}
