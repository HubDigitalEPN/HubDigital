<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

/**
 * Componente Livewire para la bandeja de préstamos del investigador.
 */
#[Layout('layouts.app', params: ['title' => 'Mis préstamos'])]
final class BandejaPrestamos extends Component
{
    use HandlesDomainExceptions;

    #[Url(as: 'q')]
    public string $busqueda = '';

    #[Url]
    public string $estado = '';

    #[Url]
    public string $ordenCampo = 'fecha_inicio';

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
        $this->ordenCampo = 'fecha_inicio';
        $this->ordenDireccion = 'desc';
    }

    /**
     * Renderiza el componente.
     *
     * @return \Illuminate\View\View
     */
    public function render(): View
    {
        $query = PrestamoEloquentModel::query()
            ->where('investigador_id', (string) auth()->id())
            ->with('acta');

        if ($this->busqueda !== '') {
            $term = $this->busqueda;
            $query->whereHas('acta', fn ($q) => $q->where('numero_prestamo', 'ilike', "%{$term}%"));
        }

        if ($this->estado !== '') {
            $query->where('estado', $this->estado);
        }

        $campo = $this->ordenCampo === 'fecha_vencimiento' ? 'fecha_fin' : 'iniciado_en';
        $prestamos = $query->orderBy($campo, $this->ordenDireccion)->get();

        return view('gestionprestamosrecepciones::investigador.bandeja-prestamos', compact('prestamos'));
    }
}
