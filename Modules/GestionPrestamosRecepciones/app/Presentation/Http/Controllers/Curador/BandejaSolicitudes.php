<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Bandeja de Solicitudes'])]
final class BandejaSolicitudes extends Component
{
    use HandlesDomainExceptions;

    #[Url(as: 'estado')]
    public string $filtroEstado = 'todos';

    public function updatedFiltroEstado(): void
    {
        // Livewire re-renders automáticamente; la query se recalcula en render()
    }

    public function render(): View
    {
        $query = SolicitudPrestamoModel::query()->orderByDesc('created_at');

        if ($this->filtroEstado !== 'todos') {
            $query->where('estado', $this->filtroEstado);
        }

        return view('gestionprestamosrecepciones::curador.bandeja-solicitudes', [
            'solicitudes' => $query->get(),
        ]);
    }
}
