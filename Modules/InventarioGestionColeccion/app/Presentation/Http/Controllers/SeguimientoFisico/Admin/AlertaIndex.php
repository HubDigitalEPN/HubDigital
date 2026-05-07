<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IgnorarAlerta\IgnorarAlertaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IgnorarAlerta\IgnorarAlertaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas\ListarAlertasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas\ListarAlertasInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverAlerta\ResolverAlertaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverAlerta\ResolverAlertaInput;

#[Layout('layouts.admin')]
final class AlertaIndex extends Component
{
    public array $alertas = [];

    public string $filtroEstado = 'activa';

    public bool $showResolverModal = false;

    public string $alertaIdParaResolver = '';

    #[Rule('required|string|min:5|max:500')]
    public string $motivoResolucion = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(ListarAlertasHandler $handler): void
    {
        $this->cargarAlertas($handler);
    }

    public function updatedFiltroEstado(string $value, ListarAlertasHandler $handler): void
    {
        $this->cargarAlertas($handler);
    }

    public function abrirResolverModal(string $alertaId): void
    {
        $this->alertaIdParaResolver = $alertaId;
        $this->motivoResolucion = '';
        $this->resetValidation();
        $this->showResolverModal = true;
    }

    public function resolver(
        ResolverAlertaHandler $handler,
        ListarAlertasHandler $listarHandler,
    ): void {
        $this->validate();

        try {
            $handler->handle(new ResolverAlertaInput(
                alertaId: $this->alertaIdParaResolver,
                motivoResolucion: $this->motivoResolucion,
            ));

            $this->cargarAlertas($listarHandler);
            $this->showResolverModal = false;
            $this->successMessage = 'Alerta resuelta correctamente.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function ignorar(
        string $alertaId,
        IgnorarAlertaHandler $handler,
        ListarAlertasHandler $listarHandler,
    ): void {
        try {
            $handler->handle(new IgnorarAlertaInput($alertaId));

            $this->cargarAlertas($listarHandler);
            $this->successMessage = 'Alerta ignorada.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    private function cargarAlertas(ListarAlertasHandler $handler): void
    {
        $estado = $this->filtroEstado !== 'todas' ? $this->filtroEstado : null;
        $output = $handler->handle(new ListarAlertasInput($estado));

        $this->alertas = array_map(
            fn ($a) => [
                'id' => $a->id,
                'cajaId' => $a->cajaId,
                'tipo' => $a->tipo,
                'estado' => $a->estado,
                'datosContexto' => $a->datosContexto,
            ],
            $output->items,
        );
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.alertas.index');
    }
}
