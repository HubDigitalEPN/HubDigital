<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use App\Models\User;
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
        $query = SolicitudPrestamoModel::query()
            ->whereNotIn('estado', ['borrador'])
            ->orderByDesc('created_at');

        if ($this->filtroEstado !== 'todos') {
            $query->where('estado', $this->filtroEstado);
        }

        $solicitudes = $query->get();

        $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $validIds = $solicitudes
            ->pluck('investigador_id')
            ->filter(fn (string $id) => preg_match($uuidRegex, $id))
            ->unique()
            ->values();

        $investigadores = User::whereIn('id', $validIds)->get()->keyBy('id');

        return view('gestionprestamosrecepciones::curador.bandeja-solicitudes', [
            'solicitudes'   => $solicitudes,
            'investigadores' => $investigadores,
        ]);
    }
}
