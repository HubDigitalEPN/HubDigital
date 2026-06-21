<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarSolicitudPrestamo\AprobarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarSolicitudPrestamo\AprobarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleSolicitud\ConsultarDetalleSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleSolicitud\ConsultarDetalleSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo\ObservarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo\ObservarSolicitudPrestamoInput;

/**
 * Componente Livewire para la revisión, aprobación o devolución de una solicitud de préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Revisar solicitud'])]
final class RevisarSolicitud extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public string $nombreInvestigador = '';

    /** Duración propuesta por el investigador (meses), capturada en mount para la aprobación. */
    public ?int $duracionPropuesta = null;

    // ── Modal: devolver con observación ──────────────────────────────────────

    public bool $showMotivoModal = false;

    #[Validate('required|string|min:10')]
    public string $motivoObservacion = '';

    // ── Modal: formulario de aprobación ──────────────────────────────────────

    public bool $showAprobacionModal = false;

    #[Validate('required|in:temporal,permanente')]
    public string $tipoPrestamo = 'temporal';

    public bool $usarDuracionPropuesta = true;

    #[Validate('required|integer|min:1|max:60')]
    public int $duracionPersonalizadaMeses = 3;

    public string $condicionesGenerales = '';

    /** @var array<string, string> itemId => condicion */
    public array $condicionesPorItem = [];

    /**
     * @param string $id
     * @param ConsultarDetalleSolicitudHandler $detalleHandler
     * @param UsuarioNombrePort $usuarios
     * @return void
     */
    public function mount(string $id, ConsultarDetalleSolicitudHandler $detalleHandler, UsuarioNombrePort $usuarios): void
    {
        $this->id = $id;

        $detalle = $detalleHandler->handle(new ConsultarDetalleSolicitudInput($id));
        abort_if($detalle === null, 404);

        $this->nombreInvestigador = $usuarios->obtenerNombre($detalle->investigadorId) ?? $detalle->investigadorId;
        $this->duracionPropuesta = $detalle->duracionPropuestaMeses;
        $this->duracionPersonalizadaMeses = $detalle->duracionPropuestaMeses ?? 3;
    }

    /**
     * @param AprobarSolicitudPrestamoHandler $handler
     * @return void
     */
    public function aprobar(AprobarSolicitudPrestamoHandler $handler): void
    {
        $this->validate([
            'tipoPrestamo' => 'required|in:temporal,permanente',
            'duracionPersonalizadaMeses' => 'required|integer|min:1|max:60',
        ]);

        $duracion = $this->usarDuracionPropuesta
            ? ($this->duracionPropuesta ?? $this->duracionPersonalizadaMeses)
            : $this->duracionPersonalizadaMeses;

        $condicionesPorItem = array_filter(
            $this->condicionesPorItem,
            fn (string $c) => trim($c) !== ''
        );

        $handler->handle(new AprobarSolicitudPrestamoInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
            tipoPrestamo: $this->tipoPrestamo,
            duracionMeses: $duracion,
            condicionesPorItem: $condicionesPorItem,
            condicionesGenerales: trim($this->condicionesGenerales) !== ''
                ? trim($this->condicionesGenerales)
                : null,
        ));

        $this->redirectRoute('prestamos.curador.actas', navigate: true);
    }

    /**
     * @param ObservarSolicitudPrestamoHandler $handler
     * @return void
     */
    public function devolver(ObservarSolicitudPrestamoHandler $handler): void
    {
        $this->validateOnly('motivoObservacion');

        $handler->handle(new ObservarSolicitudPrestamoInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
            observacion: $this->motivoObservacion,
        ));

        $this->showMotivoModal = false;
        $this->motivoObservacion = '';

        $this->redirectRoute('prestamos.curador.solicitudes', navigate: true);
    }

    /**
     * @param ConsultarHistorialSolicitudHandler $historialHandler
     * @param ConsultarDetalleSolicitudHandler $detalleHandler
     * @return View
     */
    public function render(
        ConsultarHistorialSolicitudHandler $historialHandler,
        ConsultarDetalleSolicitudHandler $detalleHandler,
    ): View {
        $solicitud = $detalleHandler->handle(new ConsultarDetalleSolicitudInput($this->id));

        $historial = $historialHandler->handle(new ConsultarHistorialSolicitudInput(
            solicitudId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        return view('gestionprestamosrecepciones::curador.revisar-solicitud', compact('solicitud', 'historial'));
    }
}
