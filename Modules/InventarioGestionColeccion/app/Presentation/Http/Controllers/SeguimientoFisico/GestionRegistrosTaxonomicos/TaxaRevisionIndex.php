<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim\ConfirmarTaxonCanonicoParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim\ConfirmarTaxonCanonicoParaVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxonVerbatimsPendientes\ListarTaxonVerbatimsPendientesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarTaxonVerbatimsPendientes\ListarTaxonVerbatimsPendientesInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Revisión de taxa'])]
final class TaxaRevisionIndex extends Component
{
    use TraduceErroresPersistencia;

    /**
     * @var list<array{
     *   verbatim: string,
     *   totalEspecimenes: int,
     *   candidatos: list<array{taxonId:string, nombreCientifico:string, rango:string, puntajeSimilitud:float}>,
     *   taxonSeleccionado: string,
     * }>
     */
    public array $items = [];

    public int $total = 0;

    public int $pagina = 1;

    public int $porPagina = 20;

    public int $limiteCandidatos = 5;

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
            ], $output->items);
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function siguientePagina(ListarTaxonVerbatimsPendientesHandler $handler): void
    {
        $maxPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);
        if ($this->pagina < $maxPaginas) {
            $this->pagina++;
            $this->cargar($handler);
        }
    }

    public function paginaAnterior(ListarTaxonVerbatimsPendientesHandler $handler): void
    {
        if ($this->pagina > 1) {
            $this->pagina--;
            $this->cargar($handler);
        }
    }

    public function seleccionarCandidato(int $idx, string $taxonId): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }
        $this->items[$idx]['taxonSeleccionado'] = $taxonId;
    }

    public function confirmar(ConfirmarTaxonCanonicoParaVerbatimHandler $handler, int $idx): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }
        $row = $this->items[$idx];
        if (($row['taxonSeleccionado'] ?? '') === '') {
            $this->errorMessage = 'Selecciona un taxón canónico antes de confirmar.';

            return;
        }

        try {
            $output = $handler->handle(new ConfirmarTaxonCanonicoParaVerbatimInput(
                verbatim: $row['verbatim'],
                taxonId: $row['taxonSeleccionado'],
            ));

            unset($this->items[$idx]);
            $this->items = array_values($this->items);
            $this->total = max(0, $this->total - 1);
            $this->successMessage = "Enlazados {$output->especimenesEnlazados} espécimen(es) al verbatim '{$row['verbatim']}'.";
            $this->errorMessage = null;
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
