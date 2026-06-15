<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionPrestamo\RegistrarDevolucionPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionPrestamo\RegistrarDevolucionPrestamoInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

#[Layout('layouts.app', params: ['title' => 'Registrar devolución'])]
final class RegistrarDevolucionPrestamo extends Component
{
    public string $id;

    public function mount(string $id): void
    {
        $this->id = $id;

        $prestamo = PrestamoEloquentModel::query()->find($id);

        if ($prestamo === null) {
            abort(404);
        }

        if ($prestamo->investigador_id !== (string) auth()->id()) {
            abort(403);
        }

        if ($prestamo->estado !== 'activo') {
            abort(403);
        }
    }

    public function registrar(RegistrarDevolucionPrestamoHandler $handler): void
    {
        $handler->handle(new RegistrarDevolucionPrestamoInput(
            prestamoId: $this->id,
            investigadorId: (string) auth()->id(),
        ));

        $this->redirect(route('prestamos.investigador.prestamo.detalle', $this->id), navigate: true);
    }

    public function render(): View
    {
        $prestamo = PrestamoEloquentModel::query()
            ->with('acta.solicitud.items')
            ->findOrFail($this->id);

        return view('gestionprestamosrecepciones::investigador.registrar-devolucion', compact('prestamo'));
    }
}
