<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra\ConfirmarMuestraHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra\ConfirmarMuestraInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeMuestra\ListarEspecimenesDeMuestraHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeMuestra\ListarEspecimenesDeMuestraInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarMuestrasParaRevision\ListarMuestrasParaRevisionHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarMuestrasParaRevision\ListarMuestrasParaRevisionInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarMuestraParaRevision\MarcarMuestraParaRevisionHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarMuestraParaRevision\MarcarMuestraParaRevisionInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SepararEspecimenesEnNuevaMuestra\SepararEspecimenesEnNuevaMuestraHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SepararEspecimenesEnNuevaMuestra\SepararEspecimenesEnNuevaMuestraInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasMuestra;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\ResolverPrioridadColumnas;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Muestras de colecta'])]
final class MuestrasColectaIndex extends Component
{
    use TraduceErroresPersistencia;

    public array $muestras = [];

    public int $total = 0;

    public int $pagina = 1;

    public int $porPagina = 25;

    /** Muestras expandidas (drill-down): muestraId => true. */
    public array $expandido = [];

    /** Especímenes cargados por muestra: muestraId => list<array>. */
    public array $miembros = [];

    /** Ids de especímenes marcados para separar, por muestra: muestraId => list<string>. */
    public array $seleccion = [];

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarMuestrasParaRevisionHandler $handler): void
    {
        $this->cargar($handler);
    }

    public function cargar(ListarMuestrasParaRevisionHandler $handler): void
    {
        try {
            $output = $handler->handle(new ListarMuestrasParaRevisionInput(
                pagina: $this->pagina,
                porPagina: $this->porPagina,
            ));

            // Reencaje: si la página quedó fuera de rango tras confirmar/descartar
            // la última muestra, vuelve al último válido y reconsulta (evita la
            // bandeja vacía con un total que dice que aún hay muestras).
            $maxPaginas = $output->total === 0
                ? 1
                : (int) ceil($output->total / max(1, $this->porPagina));
            if ($this->pagina > $maxPaginas) {
                $this->pagina = $maxPaginas;
                $output = $handler->handle(new ListarMuestrasParaRevisionInput(
                    pagina: $this->pagina,
                    porPagina: $this->porPagina,
                ));
            }

            $this->muestras = $output->items;
            $this->total = $output->total;
            // Los índices cambian al recargar; cierra los drill-downs abiertos.
            $this->expandido = [];
            $this->miembros = [];
            $this->seleccion = [];
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /** Abre/cierra el detalle de una muestra; al abrirla carga sus especímenes. */
    public function verEspecimenes(ListarEspecimenesDeMuestraHandler $handler, string $id): void
    {
        if (! empty($this->expandido[$id])) {
            $this->expandido[$id] = false;
            // Al cerrar, descarta la selección parcial para que no quede colgada.
            unset($this->seleccion[$id]);

            return;
        }

        try {
            $this->miembros[$id] = $handler->handle(new ListarEspecimenesDeMuestraInput(muestraId: $id))->items;
            // Sembrar como array (no null) es lo que permite que el primer clic
            // manual de un checkbox agregue el id en vez de guardar un booleano.
            // Por defecto, ninguno marcado: separar = elegir los ejemplares que
            // NO pertenecen a este evento de colecta.
            $this->seleccion[$id] = [];
            $this->expandido[$id] = true;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function seleccionarTodos(string $id): void
    {
        $this->seleccion[$id] = array_map(fn ($m) => $m['id'], $this->miembros[$id] ?? []);
    }

    public function limpiarSeleccion(string $id): void
    {
        $this->seleccion[$id] = [];
    }

    /**
     * Separa los especímenes marcados de una muestra hacia una muestra nueva
     * (cuando el oldCode agrupó ejemplares que no son del mismo evento). Confirmar
     * la muestra tal cual ("son del mismo grupo") sigue siendo el botón Confirmar.
     */
    public function separar(
        SepararEspecimenesEnNuevaMuestraHandler $handler,
        ListarMuestrasParaRevisionHandler $listHandler,
        string $id,
    ): void {
        $ids = array_values($this->seleccion[$id] ?? []);
        if ($ids === []) {
            $this->errorMessage = 'Marca al menos un espécimen para separarlo en una muestra nueva.';

            return;
        }

        try {
            $output = $handler->handle(new SepararEspecimenesEnNuevaMuestraInput(
                muestraOrigenId: $id,
                especimenIds: $ids,
            ));

            $this->successMessage = "Separados {$output->especimenesSeparados} espécimen(es) en una muestra nueva.";
            $this->cargar($listHandler);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function siguientePagina(ListarMuestrasParaRevisionHandler $handler): void
    {
        $maxPaginas = (int) ceil($this->total / max(1, $this->porPagina));
        if ($this->pagina < $maxPaginas) {
            $this->successMessage = null;
            $this->pagina++;
            $this->cargar($handler);
        }
    }

    public function paginaAnterior(ListarMuestrasParaRevisionHandler $handler): void
    {
        if ($this->pagina > 1) {
            $this->successMessage = null;
            $this->pagina--;
            $this->cargar($handler);
        }
    }

    public function confirmar(
        ConfirmarMuestraHandler $handler,
        ListarMuestrasParaRevisionHandler $listHandler,
        string $id,
    ): void {
        try {
            $handler->handle(new ConfirmarMuestraInput(muestraId: $id));
            $this->successMessage = 'Muestra confirmada.';
            // Recarga real: refleja el estado verdadero y reencaja la página si la
            // muestra resuelta era la última de la última página.
            $this->cargar($listHandler);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function descartar(
        MarcarMuestraParaRevisionHandler $handler,
        ListarMuestrasParaRevisionHandler $listHandler,
        string $id,
    ): void {
        try {
            // Reutilizamos marcarParaRevision con motivo "descartada por curador" como mecanismo de descarte suave.
            $handler->handle(new MarcarMuestraParaRevisionInput(
                muestraId: $id,
                motivo: 'descartada por el curador en bandeja de revisión',
            ));
            $this->successMessage = 'Muestra descartada (marcada con motivo de descarte).';
            // Antes esta acción no actualizaba la bandeja; ahora recarga como confirmar().
            $this->cargar($listHandler);
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        $totalPaginas = $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);
        $inicio = $this->total > 0 ? ($this->pagina - 1) * $this->porPagina + 1 : 0;
        $fin = min($this->pagina * $this->porPagina, $this->total);

        return view('inventariogestioncoleccion::admin.taxonomia.muestras.index', [
            'totalPaginas' => $totalPaginas,
            'inicio' => $inicio,
            'fin' => $fin,
            'columnasRegistro' => app(ResolverPrioridadColumnas::class)
                ->aplicar('muestras', RegistroColumnasMuestra::todas()),
            'columnasVisiblesPorDefecto' => RegistroColumnasMuestra::clavesVisiblesPorDefecto(),
        ]);
    }
}
