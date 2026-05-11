<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Acta de préstamo'])]
final class VerActa extends Component
{
    public string $id;

    public ?ActaPrestamoModel $acta = null;

    public function mount(string $id): void
    {
        $this->acta = ActaPrestamoModel::query()
            ->with(['solicitud.items'])
            ->findOrFail($id);

        $user = auth()->user();
        $isCurador = $user?->rol === RolUsuario::CURADOR;
        $isOwner = $this->acta->solicitud?->investigador_id === (string) $user?->id;

        if (! $isCurador && ! $isOwner) {
            abort(403);
        }
    }

    public function render(): View
    {
        return view('gestionprestamosrecepciones::ver-acta');
    }
}
