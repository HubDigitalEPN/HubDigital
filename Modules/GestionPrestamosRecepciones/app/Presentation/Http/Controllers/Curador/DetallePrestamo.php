<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
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

#[Layout('layouts.app', params: ['title' => 'Detalle del Préstamo'])]
final class DetallePrestamo extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    public string $id;

    public string $successMessage = '';

    #[Validate('required|file|mimes:pdf|max:10240')]
    public $documentoExportacion = null;

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

    public function habilitarEnvio(HabilitarEnvioInternacionalHandler $handler): void
    {
        $this->validate(['documentoExportacion' => 'required|file|mimes:pdf|max:10240']);

        $prestamoModel = PrestamoEloquentModel::findOrFail($this->id);
        // Disco privado: el documento se sirve solo vía ServirDocumentoExportacion (curador/dueño).
        $ruta = $this->documentoExportacion->store('exportaciones');

        $handler->handle(new HabilitarEnvioInternacionalInput(
            actaId: $prestamoModel->acta_prestamo_id,
            curadorId: (string) auth()->id(),
            documentoRuta: $ruta,
        ));

        $this->successMessage = 'Documento de exportación registrado. El préstamo pasa a en tránsito.';
        $this->documentoExportacion = null;
    }

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
