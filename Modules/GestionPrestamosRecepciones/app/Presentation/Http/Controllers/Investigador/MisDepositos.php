<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

#[Layout('layouts.app', params: ['title' => 'Mis Depósitos'])]
final class MisDepositos extends Component
{
    public string $filtroTipo = '';

    public string $filtroEstado = '';

    public string $filtroDesde = '';

    public string $filtroHasta = '';

    public function limpiarFiltros(): void
    {
        $this->filtroTipo = '';
        $this->filtroEstado = '';
        $this->filtroDesde = '';
        $this->filtroHasta = '';
    }

    public function render(): View
    {
        $query = SolicitudDepositoEloquentModel::where('investigador_id', (string) auth()->id())
            ->where('estado', '!=', EstadoSolicitudDeposito::EnBorrador->value);

        if ($this->filtroTipo !== '') {
            $query->where('tipo_tramite', $this->filtroTipo);
        }

        if ($this->filtroEstado !== '') {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroDesde !== '') {
            $query->whereDate('created_at', '>=', $this->filtroDesde);
        }

        if ($this->filtroHasta !== '') {
            $query->whereDate('created_at', '<=', $this->filtroHasta);
        }

        $depositos = $query->orderByDesc('created_at')->get();

        $estadosActivos = [
            EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value,
            EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value,
        ];

        $activas = $depositos->whereIn('estado', $estadosActivos);
        $historial = $depositos->whereNotIn('estado', $estadosActivos);

        $hayFiltros = $this->filtroTipo !== '' || $this->filtroEstado !== ''
            || $this->filtroDesde !== '' || $this->filtroHasta !== '';

        return view('gestionprestamosrecepciones::investigador.mis-depositos', compact(
            'depositos', 'activas', 'historial', 'hayFiltros'
        ));
    }
}
