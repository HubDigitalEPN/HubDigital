<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarVerificacionEntrega\AprobarVerificacionEntregaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarVerificacionEntrega\AprobarVerificacionEntregaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes\ConsultarVerificacionEspecimenesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes\ConsultarVerificacionEspecimenesInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;

/**
 * Componente Livewire para la aprobación de la verificación de entrega de préstamos.
 */
#[Layout('layouts.app', params: ['title' => 'Aprobar verificación de entrega'])]
final class AprobarVerificacion extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    /**
     * @param string $id
     * @param ConsultarPrestamoHandler $handler
     * @return void
     */
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

    /**
     * @param AprobarVerificacionEntregaHandler $handler
     * @return void
     */
    public function aprobar(AprobarVerificacionEntregaHandler $handler): void
    {
        $handler->handle(new AprobarVerificacionEntregaInput(
            prestamoId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->redirect(route('prestamos.curador.prestamo.detalle', $this->id), navigate: true);
    }

    /**
     * @param ConsultarPrestamoHandler $prestamoHandler
     * @param ConsultarVerificacionEspecimenesHandler $verificacionHandler
     * @return View
     */
    public function render(
        ConsultarPrestamoHandler $prestamoHandler,
        ConsultarVerificacionEspecimenesHandler $verificacionHandler,
    ): View {
        $prestamo = $prestamoHandler->handle(new ConsultarPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        $verificacion = $verificacionHandler->handle(new ConsultarVerificacionEspecimenesInput(
            prestamoId: $this->id,
            tipo: TipoVerificacion::Recepcion,
        ));

        return view('gestionprestamosrecepciones::curador.aprobar-verificacion', compact('prestamo', 'verificacion'));
    }
}
