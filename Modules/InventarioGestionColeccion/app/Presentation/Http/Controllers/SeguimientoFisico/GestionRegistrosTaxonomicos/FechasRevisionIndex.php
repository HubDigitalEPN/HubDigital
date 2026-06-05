<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarFechaParaVerbatim\ConfirmarFechaParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarFechaParaVerbatim\ConfirmarFechaParaVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarFechaVerbatimsPendientes\ListarFechaVerbatimsPendientesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarFechaVerbatimsPendientes\ListarFechaVerbatimsPendientesInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Parseo de fechas'])]
final class FechasRevisionIndex extends Component
{
    use TraduceErroresPersistencia;

    /**
     * @var list<array{
     *   verbatim: string,
     *   totalEspecimenes: int,
     *   sugerenciaInicio: ?string,
     *   sugerenciaFin: ?string,
     *   fechaInicio: string,
     *   fechaFin: string,
     * }>
     */
    public array $items = [];

    public int $total = 0;

    public int $pagina = 1;

    public int $porPagina = 20;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarFechaVerbatimsPendientesHandler $handler): void
    {
        $this->cargar($handler);
    }

    public function cargar(ListarFechaVerbatimsPendientesHandler $handler): void
    {
        try {
            $output = $handler->handle(new ListarFechaVerbatimsPendientesInput(
                pagina: $this->pagina,
                porPagina: $this->porPagina,
            ));

            $this->total = $output->totalVerbatimsDistintos;
            $this->items = array_map(fn ($item) => [
                'verbatim' => $item->verbatim,
                'totalEspecimenes' => $item->totalEspecimenes,
                'sugerenciaInicio' => $item->sugerenciaInicio,
                'sugerenciaFin' => $item->sugerenciaFin,
                // Pre-llenar con la sugerencia del parser si existe.
                'fechaInicio' => $item->sugerenciaInicio ?? '',
                'fechaFin' => $item->sugerenciaFin ?? '',
            ], $output->items);
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function siguientePagina(ListarFechaVerbatimsPendientesHandler $handler): void
    {
        $maxPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);
        if ($this->pagina < $maxPaginas) {
            $this->pagina++;
            $this->cargar($handler);
        }
    }

    public function paginaAnterior(ListarFechaVerbatimsPendientesHandler $handler): void
    {
        if ($this->pagina > 1) {
            $this->pagina--;
            $this->cargar($handler);
        }
    }

    public function confirmar(ConfirmarFechaParaVerbatimHandler $handler, int $idx): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }
        $row = $this->items[$idx];
        $fechaInicio = trim($row['fechaInicio'] ?? '');
        if ($fechaInicio === '') {
            $this->errorMessage = 'Escribe la fecha canónica (YYYY-MM-DD) antes de confirmar.';

            return;
        }
        $fechaFin = trim($row['fechaFin'] ?? '');
        $fechaFin = $fechaFin !== '' ? $fechaFin : null;

        try {
            $output = $handler->handle(new ConfirmarFechaParaVerbatimInput(
                verbatim: $row['verbatim'],
                fechaInicio: $fechaInicio,
                fechaFin: $fechaFin,
            ));

            unset($this->items[$idx]);
            $this->items = array_values($this->items);
            $this->total = max(0, $this->total - 1);
            $this->successMessage = "Asignada fecha {$output->fechaInicio} a {$output->especimenesAfectados} espécimen(es).";
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        $totalPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);

        return view('inventariogestioncoleccion::admin.taxonomia.fechas.revision', [
            'totalPaginas' => $totalPaginas,
            'inicio' => $this->total > 0 ? ($this->pagina - 1) * $this->porPagina + 1 : 0,
            'fin' => min($this->pagina * $this->porPagina, $this->total),
        ]);
    }
}
