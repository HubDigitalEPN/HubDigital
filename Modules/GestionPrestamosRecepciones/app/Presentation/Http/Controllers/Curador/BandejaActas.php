<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo\EnviarActaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarActaPrestamo\EnviarActaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

/**
 * Componente Livewire para la visualización y gestión de la bandeja de actas de préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Bandeja de actas'])]
final class BandejaActas extends Component
{
    use HandlesDomainExceptions;

    #[Url(as: 'q')]
    public string $busqueda = '';

    #[Url]
    public string $estado = '';

    #[Url(as: 'inv')]
    public string $busquedaInvestigador = '';

    #[Url]
    public string $ordenCampo = 'fecha';

    #[Url]
    public string $ordenDireccion = 'desc';

    public string $successMessage = '';

    /**
     * @return void
     */
    public function toggleOrden(): void
    {
        $this->ordenDireccion = $this->ordenDireccion === 'asc' ? 'desc' : 'asc';
    }

    /**
     * @return void
     */
    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->estado = '';
        $this->busquedaInvestigador = '';
        $this->ordenCampo = 'fecha';
        $this->ordenDireccion = 'desc';
    }

    /**
     * @param string $actaId
     * @param EnviarActaPrestamoHandler $handler
     * @return void
     */
    public function enviarActa(string $actaId, EnviarActaPrestamoHandler $handler): void
    {
        $handler->handle(new EnviarActaPrestamoInput(
            actaId: $actaId,
            curadorId: (string) auth()->id(),
        ));

        $this->successMessage = 'Acta enviada al investigador correctamente.';
    }

    /**
     * @return View
     */
    public function render(): View
    {
        $query = ActaPrestamoModel::query()
            ->with('solicitud');

        if ($this->busqueda !== '') {
            $term = $this->busqueda;
            $query->where(fn ($q) => $q
                ->where('numero_prestamo', 'ilike', "%{$term}%")
                ->orWhereHas('solicitud', fn ($sq) => $sq
                    ->where('numero_solicitud', 'ilike', "%{$term}%")
                    ->orWhere('titulo_estudio', 'ilike', "%{$term}%")
                )
            );
        }

        if ($this->estado !== '') {
            $query->where('estado', $this->estado);
        }

        if ($this->busquedaInvestigador !== '') {
            $term = $this->busquedaInvestigador;
            $ids = User::where('name', 'ilike', "%{$term}%")->pluck('id');
            $query->whereHas('solicitud', fn ($q) => $q->whereIn('investigador_id', $ids));
        }

        if ($this->ordenCampo === 'numero_solicitud') {
            $actas = $query->get();
            $actas = $this->ordenDireccion === 'asc'
                ? $actas->sortBy('solicitud.numero_solicitud')
                : $actas->sortByDesc('solicitud.numero_solicitud');
        } else {
            $actas = $query->orderBy('created_at', $this->ordenDireccion)->get();
        }

        $investigadorIds = $actas->pluck('solicitud.investigador_id')->filter()->unique();
        $investigadores = User::whereIn('id', $investigadorIds)->get()->keyBy('id');

        return view('gestionprestamosrecepciones::curador.bandeja-actas', compact('actas', 'investigadores'));
    }
}
