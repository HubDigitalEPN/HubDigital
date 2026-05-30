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
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

#[Layout('layouts.app', params: ['title' => 'Detalle de Acta'])]
final class DetalleActa extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    public string $actaId;

    public ?ActaPrestamoModel $acta = null;

    public ?PrestamoEloquentModel $prestamo = null;

    public bool $showUploadModal = false;

    /** @var TemporaryUploadedFile|null */
    public $pdfFirmado = null;

    /** @var TemporaryUploadedFile|null */
    public $documentoIdentidad = null;

    public string $successMessage = '';

    private static array $eventosActa = [
        'ActaEnviada',
        'ActaFirmadaSubida',
        'ActaDevueltaPorFirmaInvalida',
        'ActaValidada',
    ];

    public function mount(string $id): void
    {
        $this->actaId = $id;
        $this->cargarDatos();

        if ($this->acta === null) {
            abort(404);
        }

        if ($this->acta->solicitud?->investigador_id !== (string) auth()->id()) {
            abort(403);
        }
    }

    public function subirActa(SubirActaFirmadaHandler $handler): void
    {
        $this->validate([
            'pdfFirmado'         => 'required|file|mimes:pdf|max:10240',
            'documentoIdentidad' => 'required|file|mimes:pdf|max:10240',
        ]);

        $rutaActa      = Storage::putFile('actas-firmadas', $this->pdfFirmado);
        $rutaIdentidad = Storage::putFile('documentos-identidad', $this->documentoIdentidad);

        $handler->handle(new SubirActaFirmadaInput(
            solicitudId:            $this->acta->solicitud_prestamo_id,
            investigadorId:         (string) auth()->id(),
            pdfFirmadoRuta:         $rutaActa,
            documentoIdentidadRuta: $rutaIdentidad,
        ));

        $this->showUploadModal = false;
        $this->pdfFirmado = null;
        $this->documentoIdentidad = null;
        $this->successMessage = 'Documentos subidos correctamente. Espera la validación del curador.';
        $this->cargarDatos();
    }

    private function cargarDatos(): void
    {
        $this->acta = ActaPrestamoModel::query()
            ->with('solicitud')
            ->find($this->actaId);

        $this->prestamo = $this->acta
            ? PrestamoEloquentModel::query()->where('acta_prestamo_id', $this->actaId)->first()
            : null;
    }

    public function render(ConsultarHistorialSolicitudHandler $historialHandler): View
    {
        $historialActa = [];

        if ($this->acta !== null) {
            $historial = $historialHandler->handle(new ConsultarHistorialSolicitudInput(
                solicitudId: $this->acta->solicitud_prestamo_id,
                usuarioId: (string) auth()->id(),
            ));

            $historialActa = array_values(
                array_filter(
                    $historial->eventos,
                    fn ($e) => in_array($e->tipo, self::$eventosActa, true),
                )
            );
        }

        return view('gestionprestamosrecepciones::investigador.detalle-acta', compact('historialActa'));
    }
}
