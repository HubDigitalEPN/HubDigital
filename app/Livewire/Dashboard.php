<?php

namespace App\Livewire;

use App\Enums\RolUsuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        $user = Auth::user();

        return match ($user->rol) {
            RolUsuario::CURADOR => view('livewire.dashboard.curador-panel', [
                'statSolicitudesPendientes' => SolicitudPrestamoModel::query()
                    ->where('estado', 'enviada')
                    ->count(),
                'statAprobadas' => SolicitudPrestamoModel::query()
                    ->where('estado', 'aprobada')
                    ->count(),
                'statActasPorValidar' => ActaPrestamoModel::query()
                    ->where('estado', 'pendiente_validacion')
                    ->count(),
            ]),
            RolUsuario::PRESTAMISTA => view('livewire.dashboard.prestamista-panel', [
                'statTotal' => SolicitudPrestamoModel::query()
                    ->where('investigador_id', (string) $user->id)
                    ->count(),
                'statAprobadas' => SolicitudPrestamoModel::query()
                    ->where('investigador_id', (string) $user->id)
                    ->where('estado', 'aprobada')
                    ->count(),
                'statPendientes' => SolicitudPrestamoModel::query()
                    ->where('investigador_id', (string) $user->id)
                    ->whereIn('estado', ['enviada', 'observada'])
                    ->count(),
            ]),
            RolUsuario::DEPOSITANTE => view('livewire.dashboard.depositante-panel'),
        };
    }
}
