<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo\CerrarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CerrarPrestamo\CerrarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;

#[Layout('layouts.app', params: ['title' => 'Cerrar préstamo'])]
final class CerrarPrestamo extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public string $resultado = '';

    public string $observacion = '';

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

        if ($prestamo->estado->value !== 'en_revision') {
            abort(403);
        }
    }

    public function cerrar(CerrarPrestamoHandler $handler): void
    {
        $rules = ['resultado' => 'required|in:sin novedades,con observación'];

        if ($this->resultado === 'con observación') {
            $rules['observacion'] = 'required|string|min:10|max:500';
        }

        $this->validate($rules);

        $handler->handle(new CerrarPrestamoInput(
            prestamoId: $this->id,
            curadorId: (string) auth()->id(),
            resultado: $this->resultado,
            observacion: $this->resultado === 'con observación' ? trim($this->observacion) : null,
        ));

        $this->redirect(route('prestamos.curador.prestamo.detalle', $this->id), navigate: true);
    }

    public function render(ConsultarPrestamoHandler $handler): View
    {
        $prestamo = $handler->handle(new ConsultarPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        return view('gestionprestamosrecepciones::curador.cerrar-prestamo', compact('prestamo'));
    }
}
