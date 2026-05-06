<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Bandeja de Actas'])]
final class BandejaActas extends Component
{
    use HandlesDomainExceptions;

    public function render(): View
    {
        $actas = ActaPrestamoModel::query()
            ->where('estado', 'pendiente_validacion')
            ->with('solicitud')
            ->orderByDesc('created_at')
            ->get();

        return view('gestionprestamosrecepciones::curador.bandeja-actas', compact('actas'));
    }
}
