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
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas\ListarCajasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarGabinetes\ListarGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete\ListarRanurasGabineteInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverAlerta\ResolverAlertaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverAlerta\ResolverAlertaInput;

#[Layout('layouts.app', params: ['title' => 'Alertas'])]
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

    private array $cajasPorId = [];

    private array $ranurasInfo = [];

    public function mount(
        ListarAlertasHandler $alertasHandler,
        ListarCajasHandler $cajasHandler,
        ListarGabineteHandler $gabineteHandler,
        ListarRanurasGabineteHandler $ranurasHandler,
    ): void {
        $this->buildCajasPorId($cajasHandler);
        $this->buildRanurasInfo($gabineteHandler, $ranurasHandler);
        $this->cargarAlertas($alertasHandler);
    }

    public function updatedFiltroEstado(): void
    {
        $this->cargarAlertas(app(ListarAlertasHandler::class));
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

    private function buildCajasPorId(ListarCajasHandler $handler): void
    {
        $this->cajasPorId = [];
        foreach ($handler->handle()->items as $c) {
            $this->cajasPorId[$c->id] = ['codigo' => $c->codigo, 'estado' => $c->estado];
        }
    }

    private function buildRanurasInfo(
        ListarGabineteHandler $gabineteHandler,
        ListarRanurasGabineteHandler $ranurasHandler,
    ): void {
        $this->ranurasInfo = [];
        foreach ($gabineteHandler->handle()->items as $g) {
            $output = $ranurasHandler->handle(new ListarRanurasGabineteInput($g->id));
            foreach ($output->items as $r) {
                $this->ranurasInfo[$r->id] = "Ranura {$r->numeroRanura} — {$g->codigo}";
            }
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
                'cajaCodigo' => $this->cajasPorId[$a->cajaId]['codigo'] ?? '—',
                'cajaEstado' => $this->cajasPorId[$a->cajaId]['estado'] ?? null,
                'tipo' => $a->tipo,
                'estado' => $a->estado,
                'datosContexto' => $this->enriquecerContexto($a->tipo, $a->datosContexto),
                'generadaEn' => $a->generadaEn->format('d/m/Y H:i'),
            ],
            $output->items,
        );
    }

    private function enriquecerContexto(string $tipo, array $datosContexto): array
    {
        if (in_array($tipo, ['incongruencia_taxonomica', 'familia_no_asignada'], true)
            && isset($datosContexto['ranura_id'])
        ) {
            $label = $this->ranurasInfo[$datosContexto['ranura_id']] ?? $datosContexto['ranura_id'];

            return ['ranura' => $label];
        }

        return $datosContexto;
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.alertas.index');
    }
}
