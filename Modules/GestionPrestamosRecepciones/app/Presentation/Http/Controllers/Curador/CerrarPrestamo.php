<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo\CerrarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo\CerrarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ConsultarItemsPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ConsultarItemsPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;

#[Layout('layouts.app', params: ['title' => 'Cerrar préstamo'])]
final class CerrarPrestamo extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public string $resultado = '';

    public string $observacionGeneral = '';

    /** @var array<int, array{itemPrestamoId: string, descripcion: string}> */
    public array $observaciones = [];

    public function mount(
        string $id,
        ConsultarPrestamoHandler $handler,
        ConsultarItemsPrestamoHandler $itemsHandler,
    ): void {
        $this->id = $id;

        try {
            $prestamo = $handler->handle(new ConsultarPrestamoInput(
                prestamoId: $id,
                usuarioId: (string) auth()->id(),
            ));
        } catch (PrestamoNoEncontradoException) {
            abort(404);
        }

        if ($prestamo->estado->value !== 'en_revision') {
            abort(403);
        }

        // Pre-populamos para que itemPrestamoId esté disponible (wire:model en inputs ocultos no empuja valores).
        $items = $itemsHandler->handle(new ConsultarItemsPrestamoInput(prestamoId: $id))->items;

        $this->observaciones = array_map(
            fn ($item) => [
                'itemPrestamoId' => $item->itemPrestamoId,
                'descripcion' => '',
            ],
            $items,
        );
    }

    public function cerrar(CerrarPrestamoHandler $handler): void
    {
        $this->validate([
            'resultado' => 'required|in:sin novedades,con observación',
            'observaciones.*.descripcion' => 'nullable|string|max:500',
            'observacionGeneral' => 'nullable|string|max:500',
        ]);

        // Solo enviamos las novedades por espécimen que tengan descripción.
        $conObservacion = array_values(array_filter(
            $this->observaciones,
            fn (array $obs) => trim($obs['descripcion'] ?? '') !== '',
        ));

        $notaGeneral = trim($this->observacionGeneral);

        if ($this->resultado === 'con observación' && count($conObservacion) === 0 && $notaGeneral === '') {
            $this->addError('observaciones', 'Debes registrar al menos una novedad (por espécimen o en la nota general).');

            return;
        }

        $handler->handle(new CerrarPrestamoInput(
            prestamoId: $this->id,
            curadorId: (string) auth()->id(),
            resultado: $this->resultado,
            observaciones: $this->resultado === 'con observación' ? $conObservacion : [],
            observacionGeneral: $this->resultado === 'con observación' && $notaGeneral !== '' ? $notaGeneral : null,
        ));

        $this->redirect(route('prestamos.curador.prestamo.detalle', $this->id), navigate: true);
    }

    public function render(
        ConsultarPrestamoHandler $handler,
        ConsultarItemsPrestamoHandler $itemsHandler,
    ): View {
        $prestamo = $handler->handle(new ConsultarPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        $items = $itemsHandler->handle(new ConsultarItemsPrestamoInput(prestamoId: $this->id))->items;

        return view('gestionprestamosrecepciones::curador.cerrar-prestamo', compact('prestamo', 'items'));
    }
}
