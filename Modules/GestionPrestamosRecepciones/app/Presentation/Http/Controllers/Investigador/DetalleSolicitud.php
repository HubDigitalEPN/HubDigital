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
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Detalle de Solicitud'])]
final class DetalleSolicitud extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    public string $id;

    public ?SolicitudPrestamoModel $solicitud = null;

    public ?ActaPrestamoModel $acta = null;

    public bool $showUploadModal = false;

    /** @var TemporaryUploadedFile|null */
    public $pdfFirmado = null;

    public string $successMessage = '';

    public function mount(string $id): void
    {
        $this->id = $id;
        $this->cargarDatos();

        if ($this->solicitud && $this->solicitud->investigador_id !== (string) auth()->id()) {
            abort(403);
        }
    }

    public function subirActa(SubirActaFirmadaHandler $handler): void
    {
        $this->validate([
            'pdfFirmado' => 'required|file|mimes:pdf|max:10240',
        ]);

        $ruta = Storage::putFile('actas-firmadas', $this->pdfFirmado);

        $handler->handle(new SubirActaFirmadaInput(
            solicitudId: $this->id,
            investigadorId: (string) auth()->id(),
            pdfFirmadoRuta: $ruta,
        ));

        $this->showUploadModal = false;
        $this->pdfFirmado = null;
        $this->successMessage = 'Acta firmada subida correctamente. Espera la validación del curador.';
        $this->cargarDatos();
    }

    private function cargarDatos(): void
    {
        $this->solicitud = SolicitudPrestamoModel::query()->find($this->id);
        $this->acta = ActaPrestamoModel::query()
            ->where('solicitud_prestamo_id', $this->id)
            ->latest()
            ->first();
    }

    public function render(): View
    {
        return view('gestionprestamosrecepciones::investigador.detalle-solicitud');
    }
}
