<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaPrestamo\GenerarActaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaPrestamo\GenerarActaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo\ObservarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo\ObservarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Revisar Solicitud'])]
final class RevisarSolicitud extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public ?SolicitudPrestamoModel $solicitud = null;

    public bool $showMotivoModal = false;

    #[Validate('required|string|min:10')]
    public string $motivoObservacion = '';

    public function mount(string $id): void
    {
        $this->id = $id;
        $this->solicitud = SolicitudPrestamoModel::query()->with('items')->findOrFail($id);
    }

    public function aprobar(GenerarActaPrestamoHandler $handler): void
    {
        $handler->handle(new GenerarActaPrestamoInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->redirectRoute('prestamos.curador.actas', navigate: true);
    }

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

    public function render(): View
    {
        return view('gestionprestamosrecepciones::curador.revisar-solicitud');
    }
}
