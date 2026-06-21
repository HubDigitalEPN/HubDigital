<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico\ActualizarRecordatoriosPrestamoEspecificoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico\ActualizarRecordatoriosPrestamoEspecificoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetallePrestamo\ConsultarDetallePrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetallePrestamo\ConsultarDetallePrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarRecordatoriosPrestamo\ConsultarRecordatoriosPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarRecordatoriosPrestamo\ConsultarRecordatoriosPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes\ConsultarVerificacionEspecimenesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes\ConsultarVerificacionEspecimenesInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional\HabilitarEnvioInternacionalHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional\HabilitarEnvioInternacionalInput;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;

/**
 * Componente Livewire para la auditoría y gestión de detalles de un préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Auditoría de préstamo'])]
final class AuditarPrestamo extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    public string $prestamoId;

    public string $successMessage = '';

    #[Validate('required|file|mimes:pdf|max:10240')]
    public $documentoExportacion = null;

    /** @var list<array{diasAntes: int, fecha: string}> */
    public array $recordatoriosPersonalizados = [];

    public bool $mostrarModalRecordatorios = false;

    /** @var list<int> */
    public array $diasAntesModal = [];

    public string $nuevoDiaModal = '';

    /**
     * @param string $id
     * @param ConsultarDetallePrestamoHandler $detalleHandler
     * @param ConsultarRecordatoriosPrestamoHandler $recordatoriosHandler
     * @return void
     */
    public function mount(
        string $id,
        ConsultarDetallePrestamoHandler $detalleHandler,
        ConsultarRecordatoriosPrestamoHandler $recordatoriosHandler,
    ): void {
        $this->prestamoId = $id;

        if ($detalleHandler->handle(new ConsultarDetallePrestamoInput(prestamoId: $id)) === null) {
            abort(404);
        }

        $this->cargarRecordatorios($recordatoriosHandler);
    }

    /**
     * @return void
     */
    public function abrirModalRecordatorios(): void
    {
        $this->diasAntesModal = count($this->recordatoriosPersonalizados) > 0
            ? array_column($this->recordatoriosPersonalizados, 'diasAntes')
            : [30, 15, 7, 1];

        $this->nuevoDiaModal = '';
        $this->mostrarModalRecordatorios = true;
    }

    /**
     * @param int $dia
     * @return void
     */
    public function toggleDiaModal(int $dia): void
    {
        if (in_array($dia, array_map('intval', $this->diasAntesModal), true)) {
            $this->diasAntesModal = array_values(
                array_filter($this->diasAntesModal, fn (mixed $d) => (int) $d !== $dia)
            );
        } else {
            $this->diasAntesModal[] = $dia;
            rsort($this->diasAntesModal);
        }
    }

    /**
     * @return void
     */
    public function agregarDiaModal(): void
    {
        $dia = (int) $this->nuevoDiaModal;

        if ($dia < 1 || in_array($dia, array_map('intval', $this->diasAntesModal), true)) {
            $this->nuevoDiaModal = '';

            return;
        }

        $this->diasAntesModal[] = $dia;
        rsort($this->diasAntesModal);
        $this->nuevoDiaModal = '';
    }

    /**
     * @param int $dia
     * @return void
     */
    public function quitarDiaModal(int $dia): void
    {
        $this->diasAntesModal = array_values(
            array_filter($this->diasAntesModal, fn (mixed $d) => (int) $d !== $dia)
        );
    }

    /**
     * @param ActualizarRecordatoriosPrestamoEspecificoHandler $handler
     * @param ConsultarRecordatoriosPrestamoHandler $recordatoriosHandler
     * @return void
     */
    public function actualizarRecordatorios(
        ActualizarRecordatoriosPrestamoEspecificoHandler $handler,
        ConsultarRecordatoriosPrestamoHandler $recordatoriosHandler,
    ): void {
        $this->validate([
            'diasAntesModal' => ['required', 'array', 'min:1'],
            'diasAntesModal.*' => ['integer', 'min:1'],
        ]);

        try {
            $handler->handle(new ActualizarRecordatoriosPrestamoEspecificoInput(
                prestamoId: $this->prestamoId,
                curadorId: (string) auth()->id(),
                diasAntes: array_values(array_map('intval', $this->diasAntesModal)),
            ));

            $this->cargarRecordatorios($recordatoriosHandler);
            $this->mostrarModalRecordatorios = false;
        } catch (\Throwable $e) {
            $this->dispatch('domain-error', message: $e->getMessage());
        }
    }

    /**
     * @param HabilitarEnvioInternacionalHandler $handler
     * @return void
     */
    public function habilitarEnvio(
        ConsultarDetallePrestamoHandler $detalleHandler,
        HabilitarEnvioInternacionalHandler $handler,
    ): void {
        $this->validate(['documentoExportacion' => 'required|file|mimes:pdf|max:10240']);

        $detalle = $detalleHandler->handle(new ConsultarDetallePrestamoInput(prestamoId: $this->prestamoId));

        if ($detalle === null || $detalle->actaId === null) {
            abort(404);
        }

        $ruta = $this->documentoExportacion->store('exportaciones', 'public');

        $handler->handle(new HabilitarEnvioInternacionalInput(
            actaId: $detalle->actaId,
            curadorId: (string) auth()->id(),
            documentoRuta: $ruta,
        ));

        $this->successMessage = 'Documento registrado. El préstamo pasa a en tránsito.';
        $this->documentoExportacion = null;
    }

    private function cargarRecordatorios(ConsultarRecordatoriosPrestamoHandler $handler): void
    {
        $output = $handler->handle(new ConsultarRecordatoriosPrestamoInput(prestamoId: $this->prestamoId));

        $this->recordatoriosPersonalizados = array_map(
            fn ($r) => [
                'diasAntes' => $r->diasAntes,
                'fecha' => $r->fechaProgramada->format('d/m/Y'),
            ],
            $output->recordatorios,
        );
    }

    /**
     * @param ConsultarDetallePrestamoHandler $detalleHandler
     * @param ConsultarHistorialSolicitudHandler $historialSolicitudHandler
     * @param ConsultarHistorialPrestamoHandler $historialPrestamoHandler
     * @param ConsultarVerificacionEspecimenesHandler $verificacionHandler
     * @return View
     */
    public function render(
        ConsultarDetallePrestamoHandler $detalleHandler,
        ConsultarHistorialSolicitudHandler $historialSolicitudHandler,
        ConsultarHistorialPrestamoHandler $historialPrestamoHandler,
        ConsultarVerificacionEspecimenesHandler $verificacionHandler,
    ): View {
        $detalle = $detalleHandler->handle(new ConsultarDetallePrestamoInput(prestamoId: $this->prestamoId));

        if ($detalle === null) {
            abort(404);
        }

        $eventosSolicitud = [];

        if ($detalle->solicitudId !== null) {
            $historialSolicitud = $historialSolicitudHandler->handle(new ConsultarHistorialSolicitudInput(
                solicitudId: $detalle->solicitudId,
                usuarioId: (string) auth()->id(),
            ));
            $tiposActa = ['ActaEnviada', 'ActaFirmadaSubida', 'ActaDevueltaPorFirmaInvalida', 'ActaValidada'];
            $eventosSolicitud = array_map(
                fn ($e) => ['evento' => $e, 'origen' => in_array($e->tipo, $tiposActa) ? 'acta' : 'solicitud'],
                $historialSolicitud->eventos,
            );
        }

        $historialPrestamo = $historialPrestamoHandler->handle(new ConsultarHistorialPrestamoInput(
            prestamoId: $this->prestamoId,
            usuarioId: (string) auth()->id(),
        ));
        $eventosPrestamo = array_map(
            fn ($e) => ['evento' => $e, 'origen' => 'prestamo'],
            $historialPrestamo->eventos,
        );

        $timeline = collect([...$eventosSolicitud, ...$eventosPrestamo])
            ->sortBy(fn (array $item) => $item['evento']->ocurridoEn->getTimestamp())
            ->values()
            ->all();

        $verificacion = $verificacionHandler->handle(new ConsultarVerificacionEspecimenesInput(
            prestamoId: $this->prestamoId,
            tipo: TipoVerificacion::Recepcion,
        ));
        $verificacionCierre = in_array($detalle->estadoPrestamo->value, ['cerrado', 'cerrado_con_observacion'], true)
            ? $verificacionHandler->handle(new ConsultarVerificacionEspecimenesInput(
                prestamoId: $this->prestamoId,
                tipo: TipoVerificacion::Devolucion,
            ))
            : null;

        return view('gestionprestamosrecepciones::curador.auditar-prestamo', compact('detalle', 'timeline', 'verificacion', 'verificacionCierre'));
    }
}
