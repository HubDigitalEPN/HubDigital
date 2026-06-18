<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarCierrePrestamo\AprobarCierrePrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarCierrePrestamo\AprobarCierrePrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarVerificacionEntrega\AprobarVerificacionEntregaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarVerificacionEntrega\AprobarVerificacionEntregaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional\HabilitarEnvioInternacionalHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional\HabilitarEnvioInternacionalInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEntregaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

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

    public bool $showObservacionModal = false;

    public bool $showAprobarCierreModal = false;

    #[Validate('required|string|min:10')]
    public string $observacionCierre = '';

    /**
     * @param string $id
     * @param ConsultarPrestamoHandler $handler
     * @return void
     */
    public function mount(string $id, ConsultarPrestamoHandler $handler): void
    {
        $this->id = $id;

        try {
            $handler->handle(new ConsultarPrestamoInput(
                prestamoId: $id,
                usuarioId: (string) auth()->id(),
            ));
        } catch (PrestamoNoEncontradoException) {
            abort(404);
        }
    }

    /**
     * @param HabilitarEnvioInternacionalHandler $handler
     * @return void
     */
    public function habilitarEnvio(HabilitarEnvioInternacionalHandler $handler): void
    {
        $this->validate(['documentoExportacion' => 'required|file|mimes:pdf|max:10240']);

        $prestamoModel = PrestamoEloquentModel::findOrFail($this->id);
        $ruta = $this->documentoExportacion->store('exportaciones', 'public');

        $handler->handle(new HabilitarEnvioInternacionalInput(
            actaId: $prestamoModel->acta_prestamo_id,
            curadorId: (string) auth()->id(),
            documentoRuta: $ruta,
        ));

        $this->successMessage = 'Documento de exportación registrado. El préstamo pasa a en tránsito.';
        $this->documentoExportacion = null;
    }



    public function aprobarCierre(AprobarCierrePrestamoHandler $handler): void
    {
        $handler->handle(new AprobarCierrePrestamoInput(
            prestamoId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->showAprobarCierreModal = false;
        $this->successMessage = 'Préstamo cerrado correctamente.';
    }

    public function aprobarCierreConObservacion(AprobarCierrePrestamoHandler $handler): void
    {
        $this->validate(['observacionCierre' => 'required|string|min:10']);

        $handler->handle(new AprobarCierrePrestamoInput(
            prestamoId: $this->id,
            curadorId: (string) auth()->id(),
            observacion: $this->observacionCierre,
        ));

        $this->showObservacionModal = false;
        $this->observacionCierre = '';
        $this->successMessage = 'Préstamo cerrado con observaciones registradas.';
    }

    /**
     * @param ConsultarPrestamoHandler $prestamoHandler
     * @param ConsultarHistorialPrestamoHandler $historialHandler
     * @param VerificacionEntregaPrestamoRepositoryInterface $verificacionRepo
     * @return View
     */
    public function render(
        ConsultarPrestamoHandler $prestamoHandler,
        ConsultarHistorialPrestamoHandler $historialHandler,
        VerificacionEntregaPrestamoRepositoryInterface $verificacionRepo,
    ): View {
        $prestamo = $prestamoHandler->handle(new ConsultarPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        $historial = $historialHandler->handle(new ConsultarHistorialPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        $acta = ActaPrestamoModel::query()
            ->with('solicitud')
            ->find($prestamo->actaPrestamoId);

        $verificacion = $prestamo->estado->value === 'pendiente_aprobacion_verificacion'
            ? $verificacionRepo->buscarPorPrestamoId(PrestamoId::fromString($this->id))
            : null;

        return view('gestionprestamosrecepciones::curador.detalle-prestamo', compact('prestamo', 'historial', 'acta', 'verificacion'));
    }
}
