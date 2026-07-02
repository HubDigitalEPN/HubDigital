<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarFechaParaVerbatim\ConfirmarFechaParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarFechaParaVerbatim\ConfirmarFechaParaVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo\ListarEspecimenesDeGrupoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeGrupo\ListarEspecimenesDeGrupoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarFechaVerbatimsPendientes\ListarFechaVerbatimsPendientesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarFechaVerbatimsPendientes\ListarFechaVerbatimsPendientesInput;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Fechas por normalizar'])]
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

    /** Grupos expandidos: idx => true. */
    public array $expandido = [];

    /** Especímenes cargados de cada grupo: idx => list<array{id,codigoCatalogo,colector,localidad}>. */
    public array $miembros = [];

    /** Ids seleccionados por grupo: idx => list<string>. */
    public array $seleccion = [];

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
            // El estado de expansión/selección se reinicia porque los índices cambian.
            $this->expandido = [];
            $this->miembros = [];
            $this->seleccion = [];
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

    /**
     * Abre/cierra el detalle de un grupo. Al abrirlo por primera vez carga los
     * especímenes concretos y los deja todos seleccionados por defecto.
     */
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
                tipo: ListarEspecimenesDeGrupoInput::TIPO_FECHA,
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

    public function confirmar(
        ConfirmarFechaParaVerbatimHandler $handler,
        ListarFechaVerbatimsPendientesHandler $listHandler,
        int $idx,
    ): void {
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

        // Si el grupo está expandido, aplica solo a los especímenes marcados;
        // si está colapsado, aplica a todo el grupo (camino rápido de siempre).
        $ids = null;
        if (! empty($this->expandido[$idx])) {
            $ids = array_values($this->seleccion[$idx] ?? []);
            if ($ids === []) {
                $this->errorMessage = 'Selecciona al menos un espécimen, o cierra el detalle para aplicar a todo el grupo.';

                return;
            }
        }

        try {
            $output = $handler->handle(new ConfirmarFechaParaVerbatimInput(
                verbatim: $row['verbatim'],
                fechaInicio: $fechaInicio,
                fechaFin: $fechaFin,
                especimenIds: $ids,
            ));

            $this->successMessage = "Asignada fecha {$output->fechaInicio} a {$output->especimenesAfectados} espécimen(es).";
            // Recarga real: refleja el estado verdadero (grupos parciales que siguen
            // pendientes, backfill de páginas) en lugar de un borrado optimista.
            $this->cargar($listHandler);
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
