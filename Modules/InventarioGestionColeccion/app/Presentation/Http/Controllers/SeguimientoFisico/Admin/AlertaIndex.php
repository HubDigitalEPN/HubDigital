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
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

/**
 * Bandeja de alertas de ubicación para el curador: lista las alertas generadas por el
 * componente IoT (desorden taxonómico, tiempo de extracción excedido, etc.), filtra
 * por estado y permite resolverlas (con motivo) o ignorarlas. Enriquece cada alerta
 * con datos legibles —código de caja, etiqueta de ranura— para no mostrar UUIDs
 * crudos. Es presentación pura: delega cada acción en su caso de uso.
 */
#[Layout('layouts.app', params: ['title' => 'Alertas'])]
final class AlertaIndex extends Component
{
    use TraduceErroresPersistencia;

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

    /**
     * Prepara los índices auxiliares (cajas por id, etiquetas de ranura) que sirven
     * para traducir los UUIDs de cada alerta a texto legible, y carga la lista inicial
     * de alertas según el filtro por defecto.
     */
    public function mount(
        ListarAlertasHandler $alertasHandler,
        ListarCajasHandler $cajasHandler,
        ListarGabineteHandler $gabineteHandler,
        ListarRanurasGabineteHandler $ranurasHandler,
    ): void {
        $this->cargarProtegido(function () use ($alertasHandler, $cajasHandler, $gabineteHandler, $ranurasHandler) {
            $this->buildCajasPorId($cajasHandler);
            $this->buildRanurasInfo($gabineteHandler, $ranurasHandler);
            $this->cargarAlertas($alertasHandler);
        });
    }

    /** Recarga la lista cuando el curador cambia el filtro de estado (activa, todas, etc.). */
    public function updatedFiltroEstado(): void
    {
        $this->cargarProtegido(fn () => $this->cargarAlertas(app(ListarAlertasHandler::class)));
    }

    /** Abre el modal de resolución para una alerta, dejando el motivo en blanco y sin errores previos. */
    public function abrirResolverModal(string $alertaId): void
    {
        $this->alertaIdParaResolver = $alertaId;
        $this->motivoResolucion = '';
        $this->resetValidation();
        $this->showResolverModal = true;
    }

    /**
     * Resuelve la alerta abierta en el modal: valida el motivo obligatorio, ejecuta el
     * caso de uso, recarga la lista y confirma. Cualquier fallo se traduce a un mensaje
     * legible en lugar de romper la vista.
     */
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
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /**
     * Marca una alerta como ignorada (descartada sin resolución formal), recarga la
     * lista y confirma. Útil para falsos positivos que no requieren acción.
     */
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
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    /** Indexa las cajas por id (código y estado) para resolver rápidamente el código mostrado en cada alerta. */
    private function buildCajasPorId(ListarCajasHandler $handler): void
    {
        $this->cajasPorId = [];
        foreach ($handler->handle()->items as $c) {
            $this->cajasPorId[$c->id] = ['codigo' => $c->codigo, 'estado' => $c->estado];
        }
    }

    /**
     * Recorre todos los gabinetes y sus ranuras para construir un índice ranura_id →
     * etiqueta legible («Ranura 3 — GAB-A»), usado al enriquecer el contexto de las
     * alertas de orden taxonómico.
     */
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

    /**
     * Ejecuta el caso de uso de listado con el filtro actual (o sin filtro si es
     * «todas») y mapea cada alerta a una estructura plana para la vista, resolviendo el
     * código de caja, su estado y un contexto legible a partir de los índices auxiliares.
     */
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

    /**
     * Para las alertas ligadas a una ranura (orden taxonómico fuera de secuencia,
     * familia no asignada) reemplaza el ranura_id crudo por su etiqueta legible; para
     * el resto devuelve el contexto sin tocar.
     */
    private function enriquecerContexto(string $tipo, array $datosContexto): array
    {
        if (in_array($tipo, [
            TipoAlerta::OrdenTaxonomicoFueraDeSecuencia->value,
            TipoAlerta::FamiliaNoAsignada->value,
        ], true)
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
