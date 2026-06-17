<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

/**
 * Componente Livewire para la visualización de la bandeja de préstamos activos.
 */
#[Layout('layouts.app', params: ['title' => 'Bandeja de préstamos'])]
final class BandejaPrestamos extends Component
{
    use HandlesDomainExceptions;

    #[Url(as: 'q')]
    public string $busqueda = '';

    #[Url]
    public string $estado = '';

    #[Url(as: 'inv')]
    public string $busquedaInvestigador = '';

    #[Url]
    public string $ordenCampo = 'fecha_inicio';

    #[Url]
    public string $ordenDireccion = 'desc';

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
        $this->ordenCampo = 'fecha_inicio';
        $this->ordenDireccion = 'desc';
    }

    /**
     * @return View
     */
    public function render(): View
    {
        $query = PrestamoEloquentModel::query()
            ->with('acta');

        if ($this->busqueda !== '') {
            $term = $this->busqueda;
            $query->whereHas('acta', fn ($q) => $q->where('numero_prestamo', 'ilike', "%{$term}%"));
        }

        if ($this->estado !== '') {
            $query->where('estado', $this->estado);
        }

        if ($this->busquedaInvestigador !== '') {
            $term = $this->busquedaInvestigador;
            $ids = User::where('name', 'ilike', "%{$term}%")->pluck('id');
            $query->whereIn('investigador_id', $ids);
        }

        $campo = $this->ordenCampo === 'fecha_vencimiento' ? 'fecha_fin' : 'iniciado_en';
        $prestamos = $query->orderBy($campo, $this->ordenDireccion)->get();

        $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $validIds = $prestamos
            ->pluck('investigador_id')
            ->filter(fn (string $id) => preg_match($uuidRegex, $id))
            ->unique()
            ->values();

        $investigadores = User::whereIn('id', $validIds)->get()->keyBy('id');

        return view('gestionprestamosrecepciones::curador.bandeja-prestamos', compact('prestamos', 'investigadores'));
    }
}
