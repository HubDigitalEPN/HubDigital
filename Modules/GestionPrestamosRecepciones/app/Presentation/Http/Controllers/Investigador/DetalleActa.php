<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarFirmaDigitalConIdentidad\CompletarFirmaDigitalConIdentidadHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarFirmaDigitalConIdentidad\CompletarFirmaDigitalConIdentidadInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleActa\ConsultarDetalleActaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleActa\ConsultarDetalleActaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaDigitalmente\FirmarActaDigitalmenteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaDigitalmente\FirmarActaDigitalmenteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaInput;

/**
 * Componente Livewire para el detalle de un acta.
 */
#[Layout('layouts.app', params: ['title' => 'Detalle de Acta'])]
final class DetalleActa extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    public string $actaId;

    public string $solicitudPrestamoId = '';

    public bool $showUploadModal = false;

    public bool $showFirmaCanvasModal = false;

    public bool $showIdentidadModal = false;

    public string $firmaBase64 = '';

    /** @var TemporaryUploadedFile|null */
    public $pdfFirmado = null;

    /** @var TemporaryUploadedFile|null */
    public $documentoIdentidad = null;

    /** @var TemporaryUploadedFile|null */
    public $documentoIdentidadSolo = null;

    public string $successMessage = '';

    private static array $eventosActa = [
        'ActaEnviada',
        'ActaFirmadaSubida',
        'ActaFirmadaDigitalmente',
        'ActaDevueltaPorFirmaInvalida',
        'ActaValidada',
    ];

    /**
     * Inicializa el componente.
     *
     * @param string $id
     * @return void
     */
    public function mount(string $id, ConsultarDetalleActaHandler $handler): void
    {
        $this->actaId = $id;

        $acta = $handler->handle(new ConsultarDetalleActaInput(actaId: $id));

        if ($acta === null) {
            abort(404);
        }

        if ($acta->investigadorId !== (string) auth()->id()) {
            abort(403);
        }

        $this->solicitudPrestamoId = $acta->solicitudPrestamoId;
    }

    /**
     * Sube un acta firmada.
     *
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaHandler $handler
     * @return void
     */
    public function subirActa(SubirActaFirmadaHandler $handler, ConsultarDetalleActaHandler $detalleHandler): void
    {
        $acta = $detalleHandler->handle(new ConsultarDetalleActaInput(actaId: $this->actaId));

        // Si el curador devolvió solo el acta, la identidad sigue válida y no se recarga.
        $necesitaIdentidad = $acta === null || $acta->documentoIdentidadRuta === null;

        $reglas = ['pdfFirmado' => 'required|file|mimes:pdf|max:10240'];
        if ($necesitaIdentidad) {
            $reglas['documentoIdentidad'] = 'required|file|mimes:pdf|max:10240';
        }
        $this->validate($reglas);

        $rutaActa = Storage::putFile('actas-firmadas', $this->pdfFirmado);
        $rutaIdentidad = $necesitaIdentidad
            ? Storage::putFile('documentos-identidad', $this->documentoIdentidad)
            : null;

        $handler->handle(new SubirActaFirmadaInput(
            solicitudId: $this->solicitudPrestamoId,
            investigadorId: (string) auth()->id(),
            pdfFirmadoRuta: $rutaActa,
            documentoIdentidadRuta: $rutaIdentidad,
        ));

        $this->showUploadModal = false;
        $this->pdfFirmado = null;
        $this->documentoIdentidad = null;
        $this->successMessage = $necesitaIdentidad
            ? 'Documentos subidos correctamente. Espera la validación del curador.'
            : 'Acta firmada subida correctamente. Espera la validación del curador.';
    }

    public function cancelarUploadActa(): void
    {
        $this->pdfFirmado = null;
        $this->documentoIdentidad = null;
        $this->showUploadModal = false;
    }

    public function limpiarPdfFirmado(): void
    {
        $this->pdfFirmado = null;
    }

    public function limpiarDocumentoIdentidad(): void
    {
        $this->documentoIdentidad = null;
    }

    public function cancelarUploadIdentidad(): void
    {
        $this->documentoIdentidadSolo = null;
        $this->showIdentidadModal = false;
    }

    public function limpiarDocumentoIdentidadSolo(): void
    {
        $this->documentoIdentidadSolo = null;
    }

    /**
     * Firma el acta digitalmente.
     *
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaDigitalmente\FirmarActaDigitalmenteHandler $handler
     * @return void
     */
    public function firmarDigitalmente(FirmarActaDigitalmenteHandler $handler): void
    {
        $this->validate(['firmaBase64' => 'required|string']);

        $output = $handler->handle(new FirmarActaDigitalmenteInput(
            actaId: $this->actaId,
            investigadorId: (string) auth()->id(),
            firmaBase64: $this->firmaBase64,
        ));

        $this->showFirmaCanvasModal = false;
        $this->firmaBase64 = '';

        // Si la identidad ya seguía válida (devolución de solo el acta), la firma
        // completa el acta y pasa directo a validación; si no, falta la identidad.
        $this->successMessage = $output->estadoActa === 'pendiente_validacion'
            ? 'Acta firmada digitalmente. Espera la validación del curador.'
            : 'Firma registrada. Ahora sube tu documento de identidad (cédula o pasaporte) para completar el proceso.';
    }

    /**
     * Sube el documento de identidad.
     *
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarFirmaDigitalConIdentidad\CompletarFirmaDigitalConIdentidadHandler $handler
     * @return void
     */
    public function subirDocumentoIdentidad(CompletarFirmaDigitalConIdentidadHandler $handler): void
    {
        $this->validate([
            'documentoIdentidadSolo' => 'required|file|mimes:pdf|max:10240',
        ]);

        $rutaIdentidad = Storage::putFile('documentos-identidad', $this->documentoIdentidadSolo);

        $handler->handle(new CompletarFirmaDigitalConIdentidadInput(
            actaId: $this->actaId,
            investigadorId: (string) auth()->id(),
            documentoIdentidadRuta: $rutaIdentidad,
        ));

        $this->showIdentidadModal = false;
        $this->documentoIdentidadSolo = null;
        $this->successMessage = 'Documento de identidad subido. Espera la validación del curador.';
    }

    /**
     * Renderiza el componente.
     *
     * @param ConsultarDetalleActaHandler $detalleHandler
     * @param ConsultarHistorialSolicitudHandler $historialHandler
     * @return \Illuminate\View\View
     */
    public function render(
        ConsultarDetalleActaHandler $detalleHandler,
        ConsultarHistorialSolicitudHandler $historialHandler,
    ): View {
        $acta = $detalleHandler->handle(new ConsultarDetalleActaInput(actaId: $this->actaId));

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

        return view('gestionprestamosrecepciones::investigador.detalle-acta', compact('acta', 'historialActa'));
    }
}
