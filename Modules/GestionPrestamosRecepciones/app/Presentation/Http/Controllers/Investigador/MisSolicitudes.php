<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Mis Solicitudes de Préstamo'])]
final class MisSolicitudes extends Component
{
    use HandlesDomainExceptions;

    public function render(): View
    {
        $solicitudes = SolicitudPrestamoModel::query()
            ->where('investigador_id', (string) auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return view('gestionprestamosrecepciones::investigador.mis-solicitudes', compact('solicitudes'));
    }
}
