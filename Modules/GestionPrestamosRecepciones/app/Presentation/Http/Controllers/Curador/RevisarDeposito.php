<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarJustificacionesAlertas\AceptarJustificacionesAlertasHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarJustificacionesAlertas\AceptarJustificacionesAlertasInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia\AprobarDonacionConTransferenciaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDonacionConTransferencia\AprobarDonacionConTransferenciaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola\PriorizarSolicitudEnColaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\PriorizarSolicitudEnCola\PriorizarSolicitudEnColaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarDocumentalmenteSolicitud\RechazarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarDocumentalmenteSolicitud\RechazarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarJustificacionesAlertas\RechazarJustificacionesAlertasHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarJustificacionesAlertas\RechazarJustificacionesAlertasInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrioridadSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Componente Livewire para la revisión y decisión documental de una solicitud de
 * depósito o donación por parte del curador.
 */
#[Layout('layouts.app', params: ['title' => 'Revisar solicitud'])]
final class RevisarDeposito extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public string $nombreInvestigador = '';

    // ── Modal: confirmación de aprobación ────────────────────────────────────
    public bool $showConfirmacionModal = false;

    /** Acción de aprobación a confirmar: 'deposito' | 'donacion' | 'justificaciones'. */
    public string $accionAprobar = '';

    // ── Modal: rechazo documental ────────────────────────────────────────────
    public bool $showRechazoModal = false;

    #[Validate('required|in:Subsanable,Definitivo')]
    public string $tipoRechazo = 'Subsanable';

    #[Validate('required|string|min:10')]
    public string $motivoRechazo = '';

    // ── Modal: rechazo de justificaciones de alertas ─────────────────────────
    public bool $showRechazoJustificacionesModal = false;

    /** @var string[] Tipos de alerta cuya justificación se rechaza. */
    public array $justificacionesRechazadas = [];

    /**
     * Carga la solicitud y resuelve el nombre del investigador.
     */
    public function mount(string $id, UsuarioNombrePort $usuarios): void
    {
        $this->id = $id;

        $deposito = SolicitudDepositoEloquentModel::find($id);
        abort_if($deposito === null, 404);

        $this->nombreInvestigador = $usuarios->obtenerNombre($deposito->investigador_id)
            ?? $deposito->nombre_investigador_documento
            ?? $deposito->investigador_id;
    }

    /**
     * Abre el modal de confirmación para la acción de aprobación indicada.
     */
    public function pedirConfirmacion(string $accion): void
    {
        $this->accionAprobar = $accion;
        $this->showConfirmacionModal = true;
    }

    /**
     * Ejecuta la aprobación confirmada por el curador, según la acción seleccionada.
     */
    public function confirmarAprobacion(
        AprobarDocumentalmenteSolicitudHandler $aprobarHandler,
        AprobarDonacionConTransferenciaHandler $donacionHandler,
        AceptarJustificacionesAlertasHandler $justificacionesHandler,
    ): void {
        $this->showConfirmacionModal = false;

        match ($this->accionAprobar) {
            'deposito' => $this->aprobar($aprobarHandler),
            'donacion' => $this->aprobarDonacion($donacionHandler),
            'justificaciones' => $this->aceptarJustificaciones($justificacionesHandler),
            default => null,
        };
    }

    /**
     * Aprueba documentalmente un depósito sin alertas pendientes.
     */
    public function aprobar(AprobarDocumentalmenteSolicitudHandler $handler): void
    {
        ($handler)(new AprobarDocumentalmenteSolicitudInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->dispatch('toast', message: 'Solicitud aprobada documentalmente. Código QR asignado.');
    }

    /**
     * Aprueba una donación generando el Acta de Transferencia de Dominio.
     */
    public function aprobarDonacion(AprobarDonacionConTransferenciaHandler $handler): void
    {
        ($handler)(new AprobarDonacionConTransferenciaInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->dispatch('toast', message: 'Donación aprobada. Acta de transferencia y código QR generados.');
    }

    /**
     * Acepta todas las justificaciones de alertas pendientes y aprueba la solicitud.
     */
    public function aceptarJustificaciones(AceptarJustificacionesAlertasHandler $handler): void
    {
        ($handler)(new AceptarJustificacionesAlertasInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->dispatch('toast', message: 'Justificaciones aceptadas. Solicitud aprobada documentalmente.');
    }

    /**
     * Rechaza una o más justificaciones, dejando la solicitud en "Requiere Corrección".
     */
    public function rechazarJustificaciones(RechazarJustificacionesAlertasHandler $handler): void
    {
        $this->validate(
            ['justificacionesRechazadas' => 'required|array|min:1'],
            ['justificacionesRechazadas.required' => 'Selecciona al menos una justificación que no aceptas.'],
        );

        ($handler)(new RechazarJustificacionesAlertasInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
            justificacionesRechazadas: array_values($this->justificacionesRechazadas),
        ));

        $this->redirectRoute('prestamos.curador.depositos', navigate: true);
    }

    /**
     * Rechaza documentalmente la solicitud indicando tipo (Subsanable/Definitivo) y motivo.
     */
    public function rechazar(RechazarDocumentalmenteSolicitudHandler $handler): void
    {
        $this->validate();

        ($handler)(new RechazarDocumentalmenteSolicitudInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
            tipoRechazo: $this->tipoRechazo,
            motivo: $this->motivoRechazo,
        ));

        $this->redirectRoute('prestamos.curador.depositos', navigate: true);
    }

    /**
     * Clasifica la solicitud como prioritaria en la cola de revisión.
     */
    public function priorizar(PriorizarSolicitudEnColaHandler $handler): void
    {
        ($handler)(new PriorizarSolicitudEnColaInput(
            solicitudId: $this->id,
            prioridad: PrioridadSolicitud::Prioritaria->value,
        ));

        $this->dispatch('toast', message: 'Solicitud marcada como prioritaria.');
    }

    /**
     * Recarga la solicitud, sus alertas y deriva el estado de la pantalla.
     */
    public function render(MatrizEspeciesRepositoryInterface $matrizRepo): View
    {
        $deposito = SolicitudDepositoEloquentModel::with('alertas')->find($this->id);
        abort_if($deposito === null, 404);

        $alertas = $deposito->alertas;
        $hayAlertasPendientes = $alertas->contains('estado_revision', 'Pendiente de Revisión');
        $esDonacion = $deposito->tipo_tramite === TipoTramite::Donacion->value;
        $esPendiente = $deposito->estado === 'Pendiente de Revisión por Curaduría';

        $matriz = $matrizRepo->buscarPorSolicitudId($this->id);

        // Hallazgos taxonómicos que quedarían pendientes de validación manual si se aprueba.
        $hallazgosMatriz = 0;
        if ($matriz !== null) {
            foreach ($matriz->registros() as $registro) {
                if ($registro->estado()->value === 'Validación Manual por Curaduría') {
                    $hallazgosMatriz++;
                }
            }
        }

        return view('gestionprestamosrecepciones::curador.revisar-deposito', compact(
            'deposito', 'alertas', 'hayAlertasPendientes', 'esDonacion', 'esPendiente', 'matriz', 'hallazgosMatriz',
        ));
    }
}
