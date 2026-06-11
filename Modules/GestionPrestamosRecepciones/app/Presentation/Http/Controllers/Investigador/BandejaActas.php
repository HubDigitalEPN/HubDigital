<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

/**
 * Componente Livewire para la bandeja de actas del investigador.
 */
#[Layout('layouts.app', params: ['title' => 'Mis actas de préstamo'])]
final class BandejaActas extends Component
{
    use HandlesDomainExceptions;

    #[Url(as: 'q')]
    public string $busqueda = '';

    #[Url]
    public string $estado = '';

    #[Url]
    public string $ordenCampo = 'fecha';

    #[Url]
    public string $ordenDireccion = 'desc';

    /**
     * Alterna la dirección del ordenamiento.
     *
     * @return void
     */
    public function toggleOrden(): void
    {
        $this->ordenDireccion = $this->ordenDireccion === 'asc' ? 'desc' : 'asc';
    }

    /**
     * Limpia los filtros de búsqueda y ordenamiento.
     *
     * @return void
     */
    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->estado = '';
        $this->ordenCampo = 'fecha';
        $this->ordenDireccion = 'desc';
    }

    /**
     * Renderiza el componente.
     *
     * @return \Illuminate\View\View
     */
    public function render(): View
    {
        $query = ActaPrestamoModel::query()
            ->whereHas('solicitud', fn ($q) => $q->where('investigador_id', (string) auth()->id()))
            ->with('solicitud');

        if ($this->busqueda !== '') {
            $term = $this->busqueda;
            $query->where(fn ($q) => $q
                ->where('numero_prestamo', 'ilike', "%{$term}%")
                ->orWhereHas('solicitud', fn ($sq) => $sq->where('numero_solicitud', 'ilike', "%{$term}%"))
            );
        }

        if ($this->estado !== '') {
            $query->where('estado', $this->estado);
        }

        if ($this->ordenCampo === 'numero_solicitud') {
            $actas = $query->get();
            $actas = $this->ordenDireccion === 'asc'
                ? $actas->sortBy('solicitud.numero_solicitud')
                : $actas->sortByDesc('solicitud.numero_solicitud');
        } else {
            $actas = $query->orderBy('created_at', $this->ordenDireccion)->get();
        }

        return view('gestionprestamosrecepciones::investigador.bandeja-actas', compact('actas'));
    }
}
