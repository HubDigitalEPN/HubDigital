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

#[Layout('layouts.app', params: ['title' => 'Bandeja de solicitudes'])]
final class BandejaSolicitudes extends Component
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

    public function toggleOrden(): void
    {
        $this->ordenDireccion = $this->ordenDireccion === 'asc' ? 'desc' : 'asc';
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->estado = '';
        $this->busquedaInvestigador = '';
        $this->ordenCampo = 'fecha';
        $this->ordenDireccion = 'desc';
    }

    public function render(): View
    {
        $query = SolicitudPrestamoModel::query()
            ->whereNotIn('estado', ['borrador']);

        if ($this->busqueda !== '') {
            $term = $this->busqueda;
            $query->where(fn ($q) => $q
                ->where('titulo_estudio', 'ilike', "%{$term}%")
                ->orWhere('numero_solicitud', 'ilike', "%{$term}%")
            );
        }

        if ($this->estado !== '') {
            $query->where('estado', $this->estado);
        }

        if ($this->busquedaInvestigador !== '') {
            $term = $this->busquedaInvestigador;
            $ids = User::where('name', 'ilike', "%{$term}%")->pluck('id');
            $query->whereIn('investigador_id', $ids);
        }

        $campo = $this->ordenCampo === 'titulo' ? 'titulo_estudio' : 'created_at';
        $solicitudes = $query->orderBy($campo, $this->ordenDireccion)->get();

        $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $validIds = $solicitudes
            ->pluck('investigador_id')
            ->filter(fn (string $id) => preg_match($uuidRegex, $id))
            ->unique()
            ->values();

        $investigadores = User::whereIn('id', $validIds)->get()->keyBy('id');

        return view('gestionprestamosrecepciones::curador.bandeja-solicitudes', compact('solicitudes', 'investigadores'));
    }
}
