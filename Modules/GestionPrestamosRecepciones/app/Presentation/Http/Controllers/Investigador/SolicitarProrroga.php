<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarProrrogaPrestamo\SolicitarProrrogaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarProrrogaPrestamo\SolicitarProrrogaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;

/**
 * Componente Livewire para que el investigador solicite una prórroga de un préstamo activo.
 */
#[Layout('layouts.app', params: ['title' => 'Solicitar prórroga'])]
final class SolicitarProrroga extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    #[Validate('required|date|after:today')]
    public string $nuevaFechaFin = '';

    #[Validate('required|string|min:10')]
    public string $justificacion = '';

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

        // Solo se permite solicitar prórroga sobre un préstamo activo (un préstamo
        // vencido ya no la admite — satisface el escenario "no permitida con vencido").
        if ($prestamo->estado->value !== 'activo') {
            abort(403);
        }
    }

    public function solicitar(SolicitarProrrogaPrestamoHandler $handler): void
    {
        $this->validate();

        $handler->handle(new SolicitarProrrogaPrestamoInput(
            prestamoId: $this->id,
            investigadorId: (string) auth()->id(),
            nuevaFechaFin: $this->nuevaFechaFin,
            justificacion: $this->justificacion,
        ));

        session()->flash('estado', 'Tu solicitud de prórroga fue enviada y está en revisión del curador.');

        $this->redirect(route('prestamos.investigador.prestamo.detalle', $this->id), navigate: true);
    }

    public function render(ConsultarPrestamoHandler $handler): View
    {
        $prestamo = $handler->handle(new ConsultarPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        return view('gestionprestamosrecepciones::investigador.solicitar-prorroga', compact('prestamo'));
    }
}
