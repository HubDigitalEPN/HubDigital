<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;

#[Layout('layouts.app', params: ['title' => 'Detalle del Préstamo'])]
final class DetallePrestamo extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public function mount(string $id, ConsultarPrestamoHandler $handler): void
    {
        $this->id = $id;

        try {
            $handler->handle(new ConsultarPrestamoInput(
                prestamoId: $id,
                usuarioId: (string) auth()->id(),
            ));
        } catch (PrestamoNoEncontradoException) {
            abort(404);
        }
    }

    public function render(
        ConsultarPrestamoHandler $prestamoHandler,
        ConsultarHistorialPrestamoHandler $historialHandler,
    ): View {
        $prestamo = $prestamoHandler->handle(new ConsultarPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        $historial = $historialHandler->handle(new ConsultarHistorialPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        return view('gestionprestamosrecepciones::curador.detalle-prestamo', compact('prestamo', 'historial'));
    }
}
