<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra\ConfirmarMuestraHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarMuestra\ConfirmarMuestraInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarMuestrasParaRevision\ListarMuestrasParaRevisionHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarMuestrasParaRevision\ListarMuestrasParaRevisionInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarMuestraParaRevision\MarcarMuestraParaRevisionHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarMuestraParaRevision\MarcarMuestraParaRevisionInput;
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
            $this->muestras = $output->items;
            $this->total = $output->total;
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function siguientePagina(ListarMuestrasParaRevisionHandler $handler): void
    {
        $maxPaginas = (int) ceil($this->total / max(1, $this->porPagina));
        if ($this->pagina < $maxPaginas) {
            $this->pagina++;
            $this->cargar($handler);
        }
    }

    public function paginaAnterior(ListarMuestrasParaRevisionHandler $handler): void
    {
        if ($this->pagina > 1) {
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
            // Recarga real: refleja el estado verdadero y hace backfill de páginas.
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
