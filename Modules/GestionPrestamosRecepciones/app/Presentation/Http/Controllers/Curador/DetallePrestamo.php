<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetallePrestamo\ConsultarDetallePrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetallePrestamo\ConsultarDetallePrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes\ConsultarVerificacionEspecimenesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes\ConsultarVerificacionEspecimenesInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional\HabilitarEnvioInternacionalHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional\HabilitarEnvioInternacionalInput;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;

/**
 * Componente Livewire para la visualización de los detalles de un préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Detalle del Préstamo'])]
final class DetallePrestamo extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    public string $id;

    public string $successMessage = '';

    #[Validate('required|file|mimes:pdf|max:10240')]
    public $documentoExportacion = null;

    /**
     * @param string $id
     * @param ConsultarDetallePrestamoHandler $handler
     * @return void
     */
    public function mount(string $id, ConsultarDetallePrestamoHandler $handler): void
    {
        $this->id = $id;

        if ($handler->handle(new ConsultarDetallePrestamoInput(prestamoId: $id)) === null) {
            abort(404);
        }
    }

    /**
     * @param ConsultarDetallePrestamoHandler $detalleHandler
     * @param HabilitarEnvioInternacionalHandler $handler
     * @return void
     */
    public function habilitarEnvio(
        ConsultarDetallePrestamoHandler $detalleHandler,
        HabilitarEnvioInternacionalHandler $handler,
    ): void {
        $this->validate(['documentoExportacion' => 'required|file|mimes:pdf|max:10240']);

        $detalle = $detalleHandler->handle(new ConsultarDetallePrestamoInput(prestamoId: $this->id));

        if ($detalle === null || $detalle->actaId === null) {
            abort(404);
        }

        $ruta = $this->documentoExportacion->store('exportaciones', 'public');

        $handler->handle(new HabilitarEnvioInternacionalInput(
            actaId: $detalle->actaId,
            curadorId: (string) auth()->id(),
            documentoRuta: $ruta,
        ));

        $this->successMessage = 'Documento de exportación registrado. El préstamo pasa a en tránsito.';
        $this->documentoExportacion = null;
    }

    /**
     * @param ConsultarDetallePrestamoHandler $detalleHandler
     * @param ConsultarHistorialPrestamoHandler $historialHandler
     * @param ConsultarVerificacionEspecimenesHandler $verificacionHandler
     * @return View
     */
    public function render(
        ConsultarDetallePrestamoHandler $detalleHandler,
        ConsultarHistorialPrestamoHandler $historialHandler,
        ConsultarVerificacionEspecimenesHandler $verificacionHandler,
    ): View {
        $detalle = $detalleHandler->handle(new ConsultarDetallePrestamoInput(prestamoId: $this->id));

        if ($detalle === null) {
            abort(404);
        }

        $historial = $historialHandler->handle(new ConsultarHistorialPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        $verificacion = $detalle->estadoPrestamo->value === 'pendiente_aprobacion_verificacion'
            ? $verificacionHandler->handle(new ConsultarVerificacionEspecimenesInput(
                prestamoId: $this->id,
                tipo: TipoVerificacion::Recepcion,
            ))
            : null;

        $verificacionCierre = in_array($detalle->estadoPrestamo->value, ['cerrado', 'cerrado_con_observacion'], true)
            ? $verificacionHandler->handle(new ConsultarVerificacionEspecimenesInput(
                prestamoId: $this->id,
                tipo: TipoVerificacion::Devolucion,
            ))
            : null;

        return view('gestionprestamosrecepciones::curador.detalle-prestamo', compact('detalle', 'historial', 'verificacion', 'verificacionCierre'));
    }
}
