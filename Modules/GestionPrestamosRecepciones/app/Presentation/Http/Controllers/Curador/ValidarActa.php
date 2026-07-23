<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActa\ConsultarActaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActa\ConsultarActaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar\DevolverActaParaRefirmarHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar\DevolverActaParaRefirmarInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaCuradorDigitalmente\FirmarActaCuradorDigitalmenteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaCuradorDigitalmente\FirmarActaCuradorDigitalmenteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaInput;

/**
 * Componente Livewire para que el curador valide un acta adjuntando su firma:
 * dibujándola en canvas o cargando el PDF firmado.
 */
#[Layout('layouts.app', params: ['title' => 'Validar acta firmada'])]
final class ValidarActa extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    public string $id;

    public string $successMessage = '';

    public bool $showMotivoModal = false;

    public bool $showFirmaCanvasModal = false;

    public bool $showUploadModal = false;

    #[Validate('required|string|min:10')]
    public string $motivoDevolucion = '';

    public bool $devolverActa = true;

    public bool $devolverIdentidad = true;

    public string $firmaBase64 = '';

    /** @var TemporaryUploadedFile|null */
    public $pdfFirmadoCurador = null;

    public function mount(string $id, ConsultarActaHandler $handler): void
    {
        $this->id = $id;

        if ($handler->handle(new ConsultarActaInput(actaId: $id)) === null) {
            abort(404);
        }
    }

    /**
     * El curador firma el acta dibujando su firma en canvas.
     */
    public function firmarConCanvas(FirmarActaCuradorDigitalmenteHandler $handler): void
    {
        $this->validate(['firmaBase64' => 'required|string']);

        $handler->handle(new FirmarActaCuradorDigitalmenteInput(
            actaId: $this->id,
            curadorId: (string) auth()->id(),
            firmaBase64: $this->firmaBase64,
        ));

        $this->showFirmaCanvasModal = false;
        $this->firmaBase64 = '';
        $this->successMessage = 'Acta firmada y validada. Los especímenes están siendo coordinados para el despacho al investigador. El préstamo se activará una vez que el investigador confirme la recepción.';
    }

    /**
     * El curador valida el acta cargando el PDF que firmó.
     */
    public function subirActaFirmada(ValidarActaFirmadaHandler $handler): void
    {
        $this->validate(['pdfFirmadoCurador' => 'required|file|mimes:pdf|max:10240']);

        $ruta = Storage::putFile('actas-firmadas-curador', $this->pdfFirmadoCurador);

        $handler->handle(new ValidarActaFirmadaInput(
            actaId: $this->id,
            curadorId: (string) auth()->id(),
            pdfFirmadoCuradorRuta: $ruta,
        ));

        $this->showUploadModal = false;
        $this->pdfFirmadoCurador = null;
        $this->successMessage = 'Acta firmada y validada. Los especímenes están siendo coordinados para el despacho al investigador. El préstamo se activará una vez que el investigador confirme la recepción.';
    }

    public function cancelarUploadActa(): void
    {
        $this->pdfFirmadoCurador = null;
        $this->showUploadModal = false;
    }

    public function limpiarPdfFirmadoCurador(): void
    {
        $this->pdfFirmadoCurador = null;
    }

    public function devolverParaRefirmar(DevolverActaParaRefirmarHandler $handler): void
    {
        $this->validate(['motivoDevolucion' => 'required|string|min:10']);

        if (! $this->devolverActa && ! $this->devolverIdentidad) {
            $this->addError('devolverActa', 'Selecciona al menos un documento para devolver.');

            return;
        }

        $handler->handle(new DevolverActaParaRefirmarInput(
            actaId: $this->id,
            curadorId: (string) auth()->id(),
            motivo: $this->motivoDevolucion,
            devolverActa: $this->devolverActa,
            devolverIdentidad: $this->devolverIdentidad,
        ));

        $this->redirectRoute('prestamos.curador.actas', navigate: true);
    }

    private static array $eventosActa = [
        'ActaEnviada',
        'ActaFirmadaSubida',
        'ActaFirmadaDigitalmente',
        'ActaDevueltaPorFirmaInvalida',
        'ActaValidada',
        'ActaFirmadaPorCurador',
    ];

    public function render(
        ConsultarActaHandler $actaHandler,
        ConsultarHistorialSolicitudHandler $historialHandler,
    ): View {
        $acta = $actaHandler->handle(new ConsultarActaInput(actaId: $this->id));

        if ($acta === null) {
            abort(404);
        }

        $historial = $historialHandler->handle(new ConsultarHistorialSolicitudInput(
            solicitudId: $acta->solicitudPrestamoId,
            usuarioId: (string) auth()->id(),
        ));

        $historialActa = array_values(
            array_filter(
                $historial->eventos,
                fn ($e) => in_array($e->tipo, self::$eventosActa, true),
            )
        );

        return view('gestionprestamosrecepciones::curador.validar-acta', compact('acta', 'historialActa'));
    }
}
