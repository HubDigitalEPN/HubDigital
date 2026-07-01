<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo\AprobarProrrogaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarProrrogaPrestamo\AprobarProrrogaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudProrroga\ConsultarSolicitudProrrogaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudProrroga\ConsultarSolicitudProrrogaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarProrrogaPrestamo\RechazarProrrogaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarProrrogaPrestamo\RechazarProrrogaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;

/**
 * Componente Livewire para que el curador resuelva (apruebe o rechace) una solicitud
 * de prórroga pendiente de un préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Gestionar prórroga'])]
final class GestionarProrroga extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    // ── Modal: aprobar con nueva fecha de vencimiento ────────────────────────
    public bool $showAprobarModal = false;

    #[Validate('required|date|after:today')]
    public string $nuevaFechaFin = '';

    // ── Modal: rechazar con comentario obligatorio ───────────────────────────
    public bool $showRechazarModal = false;

    #[Validate('required|string|min:10')]
    public string $comentarioRechazo = '';

    public function mount(
        string $id,
        ConsultarPrestamoHandler $prestamoHandler,
        ConsultarSolicitudProrrogaHandler $solicitudHandler,
    ): void {
        $this->id = $id;

        try {
            $prestamo = $prestamoHandler->handle(new ConsultarPrestamoInput(
                prestamoId: $id,
                usuarioId: (string) auth()->id(),
            ));
        } catch (PrestamoNoEncontradoException) {
            abort(404);
        }

        if ($prestamo->estado->value !== 'prorroga_solicitada') {
            abort(403);
        }

        // Pre-llena la fecha con la propuesta por el investigador (el curador puede ajustarla).
        $solicitud = $solicitudHandler->handle(new ConsultarSolicitudProrrogaInput($id));
        if ($solicitud !== null) {
            $this->nuevaFechaFin = $solicitud->fechaPropuesta->format('Y-m-d');
        }
    }

    public function aprobar(AprobarProrrogaPrestamoHandler $handler): void
    {
        $this->validateOnly('nuevaFechaFin');

        $handler->handle(new AprobarProrrogaPrestamoInput(
            prestamoId: $this->id,
            curadorId: (string) auth()->id(),
            nuevaFechaFin: $this->nuevaFechaFin,
        ));

        session()->flash('estado', 'La prórroga fue aprobada con la nueva fecha de vencimiento.');

        $this->redirectRoute('prestamos.curador.prestamo.detalle', ['id' => $this->id], navigate: true);
    }

    public function rechazar(RechazarProrrogaPrestamoHandler $handler): void
    {
        $this->validateOnly('comentarioRechazo');

        $handler->handle(new RechazarProrrogaPrestamoInput(
            prestamoId: $this->id,
            curadorId: (string) auth()->id(),
            comentario: $this->comentarioRechazo,
        ));

        session()->flash('estado', 'La prórroga fue rechazada; el préstamo conserva su fecha original.');

        $this->redirectRoute('prestamos.curador.prestamo.detalle', ['id' => $this->id], navigate: true);
    }

    public function render(
        ConsultarPrestamoHandler $prestamoHandler,
        ConsultarSolicitudProrrogaHandler $solicitudHandler,
    ): View {
        $prestamo = $prestamoHandler->handle(new ConsultarPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        $solicitud = $solicitudHandler->handle(new ConsultarSolicitudProrrogaInput($this->id));

        return view('gestionprestamosrecepciones::curador.gestionar-prorroga', compact('prestamo', 'solicitud'));
    }
}
