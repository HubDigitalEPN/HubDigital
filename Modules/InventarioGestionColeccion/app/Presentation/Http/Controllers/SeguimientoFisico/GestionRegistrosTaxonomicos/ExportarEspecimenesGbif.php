<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarEspecimenesSeleccionados\ExportarEspecimenesSeleccionadosHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarEspecimenesSeleccionados\ExportarEspecimenesSeleccionadosInput;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\GeneradorEspecimenesXlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sección embebida en "Publicación GBIF": permite filtrar especímenes, marcar
 * con casillas cuáles exportar (la selección persiste entre páginas) y descargar
 * SOLO los seleccionados como Excel formateado. Es capa de presentación: delega
 * la búsqueda y la construcción de la tabla en sus handlers.
 */
final class ExportarEspecimenesGbif extends Component
{
    /** Máximo de especímenes que "seleccionar todos" agrega y que se exportan. */
    private const MAX_SELECCION = 2000;

    // ── Filtros ───────────────────────────────────────────────────────────────
    public string $fTaxon = '';

    public string $fFamilia = '';

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

    /** Marca todos los especímenes que cumplen el filtro actual (hasta el tope). */
    public function seleccionarTodosFiltrados(BuscarEspecimenesHandler $handler): void
    {
        $output = $handler->handle($this->construirInput(1, self::MAX_SELECCION));

        foreach ($output->items as $item) {
            $this->seleccionados[$item['id']] = true;
        }

        $this->mensaje = $output->total > self::MAX_SELECCION
            ? 'Se seleccionaron los primeros '.self::MAX_SELECCION.' de '.$output->total.' resultados (tope de la pantalla).'
            : $this->contarSeleccionados().' especímenes seleccionados.';
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
    public function exportar(ExportarEspecimenesSeleccionadosHandler $handler): ?StreamedResponse
    {
        $ids = array_keys(array_filter($this->seleccionados));

        if ($ids === []) {
            $this->error = 'Selecciona al menos un espécimen para exportar.';

            return null;
        }

        $ids = array_slice($ids, 0, self::MAX_SELECCION);
        $output = $handler->handle(new ExportarEspecimenesSeleccionadosInput(ids: $ids));
        $xlsx = GeneradorEspecimenesXlsx::generar($output->columnas, $output->filas);

        $this->error = null;
        $this->mensaje = count($output->filas).' especímenes exportados.';

        return response()->streamDownload(
            function () use ($xlsx): void {
                echo $xlsx;
            },
            'especimenes-seleccionados.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Length' => (string) strlen($xlsx),
            ]
        );
    }

    private function construirInput(int $page, int $perPage): BuscarEspecimenesInput
    {
        return new BuscarEspecimenesInput(
            taxonNombre: $this->nullable($this->fTaxon),
            catalogNumber: $this->nullable($this->fCatalogNumber),
            localidad: $this->nullable($this->fLocalidad),
            colector: $this->nullable($this->fColector),
            familia: $this->nullable($this->fFamilia),
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
