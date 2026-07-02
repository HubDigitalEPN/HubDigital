<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarTaxonesPorNombre\BuscarTaxonesPorNombreHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarTaxonesPorNombre\BuscarTaxonesPorNombreInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim\ConfirmarTaxonCanonicoParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim\ConfirmarTaxonCanonicoParaVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo\ListarEspecimenesDeGrupoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo\ListarEspecimenesDeGrupoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxonVerbatimsPendientes\ListarTaxonVerbatimsPendientesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxonVerbatimsPendientes\ListarTaxonVerbatimsPendientesInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Taxones por confirmar'])]
final class TaxaRevisionIndex extends Component
{
    use TraduceErroresPersistencia;

    /**
     * @var list<array{
     *   verbatim: string,
     *   totalEspecimenes: int,
     *   candidatos: list<array{taxonId:string, nombreCientifico:string, rango:string, puntajeSimilitud:float}>,
     *   taxonSeleccionado: string,
     *   taxonSeleccionadoNombre: string,
     * }>
     */
    public array $items = [];

    public int $total = 0;

    public int $pagina = 1;

    public int $porPagina = 20;

    public int $limiteCandidatos = 5;

    /** Grupos expandidos: idx => true. */
    public array $expandido = [];

    /** Especímenes cargados de cada grupo: idx => list<array{id,codigoCatalogo,colector,localidad}>. */
    public array $miembros = [];

    /** Ids seleccionados por grupo: idx => list<string>. */
    public array $seleccion = [];

    /** Texto del buscador "otro nombre": idx => string. */
    public array $busquedaTexto = [];

    /** Resultados del buscador: idx => list<array{taxonId,nombreCientifico,rango}>. */
    public array $busquedaResultados = [];

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarTaxonVerbatimsPendientesHandler $handler): void
    {
        $this->cargar($handler);
    }

    public function cargar(ListarTaxonVerbatimsPendientesHandler $handler): void
    {
        try {
            $output = $handler->handle(new ListarTaxonVerbatimsPendientesInput(
                limiteCandidatosPorVerbatim: $this->limiteCandidatos,
                pagina: $this->pagina,
                porPagina: $this->porPagina,
            ));

            // Reencaje: si la página quedó fuera de rango tras confirmar el
            // último grupo, vuelve al último válido y reconsulta (evita la lista
            // vacía con un total que dice que aún hay grupos).
            $maxPaginas = $output->totalVerbatimsDistintos === 0
                ? 1
                : (int) ceil($output->totalVerbatimsDistintos / $this->porPagina);
            if ($this->pagina > $maxPaginas) {
                $this->pagina = $maxPaginas;
                $output = $handler->handle(new ListarTaxonVerbatimsPendientesInput(
                    limiteCandidatosPorVerbatim: $this->limiteCandidatos,
                    pagina: $this->pagina,
                    porPagina: $this->porPagina,
                ));
            }

            $this->total = $output->totalVerbatimsDistintos;
            $this->items = array_map(fn ($item) => [
                'verbatim' => $item->verbatim,
                'totalEspecimenes' => $item->totalEspecimenes,
                'candidatos' => array_map(fn ($c) => [
                    'taxonId' => $c->taxonId,
                    'nombreCientifico' => $c->nombreCientifico,
                    'rango' => $c->rango,
                    'puntajeSimilitud' => $c->puntajeSimilitud,
                ], $item->candidatos),
                'taxonSeleccionado' => $item->candidatos[0]->taxonId ?? '',
                'taxonSeleccionadoNombre' => $item->candidatos[0]->nombreCientifico ?? '',
            ], $output->items);
            $this->expandido = [];
            $this->miembros = [];
            $this->seleccion = [];
            $this->busquedaTexto = [];
            $this->busquedaResultados = [];
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function siguientePagina(ListarTaxonVerbatimsPendientesHandler $handler): void
    {
        $maxPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);
        if ($this->pagina < $maxPaginas) {
            $this->successMessage = null;
            $this->pagina++;
            $this->cargar($handler);
        }
    }

    public function paginaAnterior(ListarTaxonVerbatimsPendientesHandler $handler): void
    {
        if ($this->pagina > 1) {
            $this->successMessage = null;
            $this->pagina--;
            $this->cargar($handler);
        }
    }

    public function seleccionarCandidato(int $idx, string $taxonId): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }

        $nombre = '';
        foreach ($this->items[$idx]['candidatos'] ?? [] as $c) {
            if ($c['taxonId'] === $taxonId) {
                $nombre = $c['nombreCientifico'];
                break;
            }
        }
        if ($nombre === '') {
            foreach ($this->busquedaResultados[$idx] ?? [] as $r) {
                if ($r['taxonId'] === $taxonId) {
                    $nombre = $r['nombreCientifico'];
                    break;
                }
            }
        }

        $this->items[$idx]['taxonSeleccionado'] = $taxonId;
        $this->items[$idx]['taxonSeleccionadoNombre'] = $nombre;
    }

    public function verEspecimenes(ListarEspecimenesDeGrupoHandler $handler, int $idx): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }

        if (! empty($this->expandido[$idx])) {
            $this->expandido[$idx] = false;

            return;
        }

        try {
            $output = $handler->handle(new ListarEspecimenesDeGrupoInput(
                tipo: ListarEspecimenesDeGrupoInput::TIPO_TAXON,
                verbatim: $this->items[$idx]['verbatim'],
            ));

            $this->miembros[$idx] = array_map(fn ($m) => [
                'id' => $m['id'],
                'codigoCatalogo' => $m['codigoCatalogo'],
                'colector' => $m['colector'],
                'localidad' => $m['localidad'],
            ], $output->items);
            $this->seleccion[$idx] = array_map(fn ($m) => $m['id'], $output->items);
            $this->expandido[$idx] = true;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function seleccionarTodos(int $idx): void
    {
        $this->seleccion[$idx] = array_map(fn ($m) => $m['id'], $this->miembros[$idx] ?? []);
    }

    public function limpiarSeleccion(int $idx): void
    {
        $this->seleccion[$idx] = [];
    }

    /** Busca taxones canónicos más allá de los candidatos sugeridos (reasignar a otro nombre). */
    public function buscarOtros(BuscarTaxonesPorNombreHandler $handler, int $idx): void
    {
        $consulta = trim($this->busquedaTexto[$idx] ?? '');
        if ($consulta === '') {
            $this->busquedaResultados[$idx] = [];

            return;
        }

        try {
            $output = $handler->handle(new BuscarTaxonesPorNombreInput(consulta: $consulta));
            $this->busquedaResultados[$idx] = $output->items;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function confirmar(
        ConfirmarTaxonCanonicoParaVerbatimHandler $handler,
        ListarTaxonVerbatimsPendientesHandler $listHandler,
        int $idx,
    ): void {
        if (! isset($this->items[$idx])) {
            return;
        }
        $row = $this->items[$idx];
        if (($row['taxonSeleccionado'] ?? '') === '') {
            $this->errorMessage = 'Selecciona un taxón canónico antes de confirmar.';

            return;
        }

        $ids = null;
        if (! empty($this->expandido[$idx])) {
            $ids = array_values($this->seleccion[$idx] ?? []);
            if ($ids === []) {
                $this->errorMessage = 'Selecciona al menos un espécimen, o cierra el detalle para aplicar a todo el grupo.';

                return;
            }
        }

        try {
            $output = $handler->handle(new ConfirmarTaxonCanonicoParaVerbatimInput(
                verbatim: $row['verbatim'],
                taxonId: $row['taxonSeleccionado'],
                especimenIds: $ids,
            ));

            $this->successMessage = "Enlazados {$output->especimenesEnlazados} espécimen(es) al taxón seleccionado.";
            $this->cargar($listHandler);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        $totalPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);

        return view('inventariogestioncoleccion::admin.taxonomia.taxones.revision', [
            'totalPaginas' => $totalPaginas,
            'inicio' => $this->total > 0 ? ($this->pagina - 1) * $this->porPagina + 1 : 0,
            'fin' => min($this->pagina * $this->porPagina, $this->total),
        ]);
    }
}
