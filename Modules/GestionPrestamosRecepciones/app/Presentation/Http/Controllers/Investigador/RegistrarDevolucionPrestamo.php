<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ConsultarItemsPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ConsultarItemsPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionPrestamo\RegistrarDevolucionPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionPrestamo\RegistrarDevolucionPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;

#[Layout('layouts.app', params: ['title' => 'Registrar devolución'])]
final class RegistrarDevolucionPrestamo extends Component
{
    public string $id;

    public function mount(string $id, ConsultarPrestamoHandler $handler): void
    {
        $this->id = $id;

        try {
            $prestamo = $handler->handle(new ConsultarPrestamoInput(
                prestamoId: $id,
                usuarioId: (string) auth()->id(),
            ));
        } catch (PrestamoNoEncontradoException) {
            abort(404);
        }

        if ($prestamo->investigadorId !== (string) auth()->id()) {
            abort(403);
        }

        if ($prestamo->estado->value !== 'activo') {
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

    public function render(
        ConsultarPrestamoHandler $prestamoHandler,
        ConsultarItemsPrestamoHandler $itemsHandler,
    ): View {
        $prestamo = $prestamoHandler->handle(new ConsultarPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        $totalItems = $itemsHandler->handle(new ConsultarItemsPrestamoInput(
            prestamoId: $this->id,
        ))->total();

        return view('gestionprestamosrecepciones::investigador.registrar-devolucion', compact('prestamo', 'totalItems'));
    }
}
