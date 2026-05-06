<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar\DevolverActaParaRefirmarHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar\DevolverActaParaRefirmarInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Validar Acta Firmada'])]
final class ValidarActa extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public ?ActaPrestamoModel $acta = null;

    public string $successMessage = '';

    public bool $showMotivoModal = false;

    #[Validate('required|string|min:10')]
    public string $motivoDevolucion = '';

    public function mount(string $id): void
    {
        $this->id = $id;
        $this->acta = ActaPrestamoModel::query()->with('solicitud')->findOrFail($id);
    }

    public function validar(ValidarActaFirmadaHandler $handler): void
    {
        $handler->handle(new ValidarActaFirmadaInput(
            actaId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->successMessage = 'Acta validada correctamente.';
        $this->acta = ActaPrestamoModel::query()->with('solicitud')->find($this->id);
    }

    public function devolverParaRefirmar(DevolverActaParaRefirmarHandler $handler): void
    {
        $this->validate(['motivoDevolucion' => 'required|string|min:10']);

        $investigadorId = (string) $this->acta?->solicitud?->investigador_id;

        $handler->handle(new DevolverActaParaRefirmarInput(
            actaId: $this->id,
            investigadorId: $investigadorId,
            motivo: $this->motivoDevolucion,
        ));

        $this->redirectRoute('prestamos.curador.actas', navigate: true);
    }

    public function render(): View
    {
        return view('gestionprestamosrecepciones::curador.validar-acta');
    }
}
