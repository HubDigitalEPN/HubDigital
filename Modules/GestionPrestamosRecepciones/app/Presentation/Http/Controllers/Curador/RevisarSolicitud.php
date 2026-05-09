<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarSolicitudPrestamo\AprobarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarSolicitudPrestamo\AprobarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo\ObservarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo\ObservarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Revisar Solicitud'])]
final class RevisarSolicitud extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public ?SolicitudPrestamoModel $solicitud = null;

    public string $nombreInvestigador = '';

    // ── Modal: devolver con observación ──────────────────────────────────────

    public bool $showMotivoModal = false;

    #[Validate('required|string|min:10')]
    public string $motivoObservacion = '';

    // ── Modal: formulario de aprobación ──────────────────────────────────────

    public bool $showAprobacionModal = false;

    #[Validate('required|in:temporal,permanente')]
    public string $tipoPrestamo = 'temporal';

    public bool $usarDuracionPropuesta = true;

    #[Validate('required|integer|min:1|max:60')]
    public int $duracionPersonalizadaMeses = 3;

    public string $condicionesGenerales = '';

    /** @var array<string, string> itemId => condicion */
    public array $condicionesPorItem = [];

    public function mount(string $id): void
    {
        $this->id = $id;
        $this->solicitud = SolicitudPrestamoModel::query()->with('items')->findOrFail($id);

        $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $invId = $this->solicitud->investigador_id;
        $this->nombreInvestigador = preg_match($uuidRegex, $invId)
            ? (User::find($invId)?->name ?? $invId)
            : $invId;

        $this->duracionPersonalizadaMeses = $this->solicitud->duracion_propuesta_meses ?? 3;
    }

    public function aprobar(AprobarSolicitudPrestamoHandler $handler): void
    {
        $this->validate([
            'tipoPrestamo'              => 'required|in:temporal,permanente',
            'duracionPersonalizadaMeses' => 'required|integer|min:1|max:60',
        ]);

        $duracion = $this->usarDuracionPropuesta
            ? ($this->solicitud?->duracion_propuesta_meses ?? $this->duracionPersonalizadaMeses)
            : $this->duracionPersonalizadaMeses;

        $condicionesPorItem = array_filter(
            $this->condicionesPorItem,
            fn (string $c) => trim($c) !== ''
        );

        $handler->handle(new AprobarSolicitudPrestamoInput(
            solicitudId:         $this->id,
            curadorId:           (string) auth()->id(),
            tipoPrestamo:        $this->tipoPrestamo,
            duracionMeses:       $duracion,
            condicionesPorItem:  $condicionesPorItem,
            condicionesGenerales: trim($this->condicionesGenerales) !== ''
                ? trim($this->condicionesGenerales)
                : null,
        ));

        $this->redirectRoute('prestamos.curador.actas', navigate: true);
    }

    public function devolver(ObservarSolicitudPrestamoHandler $handler): void
    {
        $this->validateOnly('motivoObservacion');

        $handler->handle(new ObservarSolicitudPrestamoInput(
            solicitudId: $this->id,
            curadorId:   (string) auth()->id(),
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
