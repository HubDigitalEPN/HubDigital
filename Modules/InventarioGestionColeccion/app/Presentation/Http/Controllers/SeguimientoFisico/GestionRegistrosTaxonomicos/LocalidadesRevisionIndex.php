<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarLocalidadCanonicaParaVerbatim\ConfirmarLocalidadCanonicaParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarLocalidadCanonicaParaVerbatim\ConfirmarLocalidadCanonicaParaVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes\ListarLocalidadVerbatimsPendientesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes\ListarLocalidadVerbatimsPendientesInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Revisión de localidades'])]
final class LocalidadesRevisionIndex extends Component
{
    use TraduceErroresPersistencia;

    /**
     * @var list<array{
     *   verbatim: string,
     *   totalEspecimenes: int,
     *   candidatos: list<array{
     *     localidadId: string,
     *     nombreCanonico: string,
     *     rango: string,
     *     puntajeSimilitud: float,
     *   }>,
     *   localidadSeleccionada: string,
     * }>
     */
    public array $items = [];

    public int $total = 0;

    public int $pagina = 1;

    public int $porPagina = 20;

    public int $limiteCandidatos = 5;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarLocalidadVerbatimsPendientesHandler $handler): void
    {
        $this->cargar($handler);
    }

    public function cargar(ListarLocalidadVerbatimsPendientesHandler $handler): void
    {
        try {
            $output = $handler->handle(new ListarLocalidadVerbatimsPendientesInput(
                limiteCandidatosPorVerbatim: $this->limiteCandidatos,
                pagina: $this->pagina,
                porPagina: $this->porPagina,
            ));

            $this->total = $output->totalVerbatimsDistintos;
            $this->items = array_map(fn ($item) => [
                'verbatim' => $item->verbatim,
                'totalEspecimenes' => $item->totalEspecimenes,
                'candidatos' => array_map(fn ($c) => [
                    'localidadId' => $c->localidadId,
                    'nombreCanonico' => $c->nombreCanonico,
                    'rango' => $c->rango,
                    'puntajeSimilitud' => $c->puntajeSimilitud,
                ], $item->candidatos),
                // Pre-selecciona el candidato top.
                'localidadSeleccionada' => $item->candidatos[0]->localidadId ?? '',
            ], $output->items);
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function siguientePagina(ListarLocalidadVerbatimsPendientesHandler $handler): void
    {
        $maxPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);
        if ($this->pagina < $maxPaginas) {
            $this->pagina++;
            $this->cargar($handler);
        }
    }

    public function paginaAnterior(ListarLocalidadVerbatimsPendientesHandler $handler): void
    {
        if ($this->pagina > 1) {
            $this->pagina--;
            $this->cargar($handler);
        }
    }

    public function seleccionarCandidato(int $idx, string $localidadId): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }
        $this->items[$idx]['localidadSeleccionada'] = $localidadId;
    }

    public function confirmar(ConfirmarLocalidadCanonicaParaVerbatimHandler $handler, int $idx): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }
        $row = $this->items[$idx];
        if (($row['localidadSeleccionada'] ?? '') === '') {
            $this->errorMessage = 'Selecciona una localidad canónica antes de confirmar.';

            return;
        }

        try {
            $output = $handler->handle(new ConfirmarLocalidadCanonicaParaVerbatimInput(
                verbatim: $row['verbatim'],
                localidadId: $row['localidadSeleccionada'],
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

        return view('inventariogestioncoleccion::admin.taxonomia.localidades.revision', [
            'totalPaginas' => $totalPaginas,
            'inicio' => $this->total > 0 ? ($this->pagina - 1) * $this->porPagina + 1 : 0,
            'fin' => min($this->pagina * $this->porPagina, $this->total),
        ]);
    }
}
