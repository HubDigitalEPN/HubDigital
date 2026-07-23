<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCatalogNumberDuplicados\ListarCatalogNumberDuplicadosHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCatalogNumberDuplicados\ListarCatalogNumberDuplicadosInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverDuplicadoDeCatalogNumber\ResolverDuplicadoDeCatalogNumberHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverDuplicadoDeCatalogNumber\ResolverDuplicadoDeCatalogNumberInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Números de catálogo duplicados'])]
final class DuplicadosCatalogNumberIndex extends Component
{
    use TraduceErroresPersistencia;

    /**
     * @var list<array{
     *   catalogNumber: string,
     *   total: int,
     *   especimenes: list<array{
     *     id:string,
     *     codigoCatalogo:string,
     *     fechaColecta:string,
     *     colector:string,
     *     estadoRevision:string
     *   }>,
     *   fechasDistintas: bool,
     *   motivoInput: string,
     * }>
     */
    public array $items = [];

    public int $total = 0;

    public int $pagina = 1;

    public int $porPagina = 20;

    public int $minimoDuplicados = 2;

    /** Ids seleccionados por grupo: idx => list<string>. Vacío = aplicar a todo el grupo. */
    public array $seleccion = [];

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarCatalogNumberDuplicadosHandler $handler): void
    {
        $this->cargar($handler);
    }

    public function cargar(ListarCatalogNumberDuplicadosHandler $handler): void
    {
        try {
            $output = $handler->handle(new ListarCatalogNumberDuplicadosInput(
                minimoDuplicados: $this->minimoDuplicados,
                pagina: $this->pagina,
                porPagina: $this->porPagina,
            ));

            // Reencaje: si la página quedó fuera de rango tras resolver el último
            // grupo, vuelve al último válido y reconsulta (evita la lista vacía
            // con un total que dice que aún hay grupos).
            $maxPaginas = $output->totalGrupos === 0
                ? 1
                : (int) ceil($output->totalGrupos / $this->porPagina);
            if ($this->pagina > $maxPaginas) {
                $this->pagina = $maxPaginas;
                $output = $handler->handle(new ListarCatalogNumberDuplicadosInput(
                    minimoDuplicados: $this->minimoDuplicados,
                    pagina: $this->pagina,
                    porPagina: $this->porPagina,
                ));
            }

            $this->total = $output->totalGrupos;
            $this->items = array_map(fn ($item) => [
                'catalogNumber' => $item->catalogNumber,
                'total' => $item->total,
                'especimenes' => $item->especimenes,
                'fechasDistintas' => $item->fechasDistintas,
                'motivoInput' => '',
            ], $output->items);
            // Los índices cambian al recargar; reinicia la selección. Cada grupo
            // arranca con un ARRAY vacío: es imprescindible para que Livewire trate
            // los checkboxes como un grupo (selección múltiple) y no como un
            // booleano compartido —lo que marcaba "todos o ninguno" y rompía el
            // count() con un `false`.
            $this->seleccion = [];
            foreach (array_keys($this->items) as $i) {
                $this->seleccion[$i] = [];
            }
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function siguientePagina(ListarCatalogNumberDuplicadosHandler $handler): void
    {
        $maxPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);
        if ($this->pagina < $maxPaginas) {
            $this->successMessage = null;
            $this->pagina++;
            $this->cargar($handler);
        }
    }

    public function paginaAnterior(ListarCatalogNumberDuplicadosHandler $handler): void
    {
        if ($this->pagina > 1) {
            $this->successMessage = null;
            $this->pagina--;
            $this->cargar($handler);
        }
    }

    public function seleccionarTodos(int $idx): void
    {
        $this->seleccion[$idx] = array_map(
            fn ($e) => $e['id'],
            $this->items[$idx]['especimenes'] ?? [],
        );
    }

    public function limpiarSeleccion(int $idx): void
    {
        $this->seleccion[$idx] = [];
    }

    /**
     * Ids seleccionados del grupo, o null si no hay selección (aplica a todo).
     *
     * @return string[]|null
     */
    private function idsSeleccionados(int $idx): ?array
    {
        $sel = $this->seleccion[$idx] ?? [];
        $ids = is_array($sel) ? array_values($sel) : [];

        return $ids === [] ? null : $ids;
    }

    public function marcarEventosDistintos(
        ResolverDuplicadoDeCatalogNumberHandler $handler,
        ListarCatalogNumberDuplicadosHandler $listHandler,
        int $idx,
    ): void {
        if (! isset($this->items[$idx])) {
            return;
        }

        try {
            $output = $handler->handle(new ResolverDuplicadoDeCatalogNumberInput(
                catalogNumber: $this->items[$idx]['catalogNumber'],
                decision: ResolverDuplicadoDeCatalogNumberInput::DECISION_EVENTOS_DISTINTOS,
                motivo: '',
                especimenIds: $this->idsSeleccionados($idx),
            ));
            $this->successMessage = "Confirmados como eventos distintos: {$output->especimenesAfectados} espécimen(es).";
            // Recarga real: refleja el estado verdadero y reencaja la página si el
            // grupo resuelto era el último de la última página.
            $this->cargar($listHandler);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function marcarErrorCatalogacion(
        ResolverDuplicadoDeCatalogNumberHandler $handler,
        ListarCatalogNumberDuplicadosHandler $listHandler,
        int $idx,
    ): void {
        if (! isset($this->items[$idx])) {
            return;
        }
        $motivo = trim($this->items[$idx]['motivoInput'] ?? '');
        if ($motivo === '') {
            $this->errorMessage = 'Para marcar error de catalogación, primero escribe un motivo en el campo del grupo.';

            return;
        }

        try {
            $output = $handler->handle(new ResolverDuplicadoDeCatalogNumberInput(
                catalogNumber: $this->items[$idx]['catalogNumber'],
                decision: ResolverDuplicadoDeCatalogNumberInput::DECISION_ERROR_CATALOGACION,
                motivo: $motivo,
                especimenIds: $this->idsSeleccionados($idx),
            ));
            $this->successMessage = "Marcados con motivo para revisión: {$output->especimenesAfectados} espécimen(es).";
            $this->cargar($listHandler);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        $totalPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);

        return view('inventariogestioncoleccion::admin.taxonomia.duplicados.index', [
            'totalPaginas' => $totalPaginas,
            'inicio' => $this->total > 0 ? ($this->pagina - 1) * $this->porPagina + 1 : 0,
            'fin' => min($this->pagina * $this->porPagina, $this->total),
        ]);
    }
}
