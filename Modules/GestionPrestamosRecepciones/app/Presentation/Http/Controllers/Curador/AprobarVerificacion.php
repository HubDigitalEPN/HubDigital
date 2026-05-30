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
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEntregaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

#[Layout('layouts.app', params: ['title' => 'Aprobar verificación de entrega'])]
final class AprobarVerificacion extends Component
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

    public function aprobar(AprobarVerificacionEntregaHandler $handler): void
    {
        $handler->handle(new AprobarVerificacionEntregaInput(
            prestamoId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->redirect(route('prestamos.curador.prestamo.detalle', $this->id), navigate: true);
    }

    public function render(
        ConsultarPrestamoHandler $prestamoHandler,
        VerificacionEntregaPrestamoRepositoryInterface $verificacionRepo,
    ): View {
        $prestamo = $prestamoHandler->handle(new ConsultarPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        $verificacion = $verificacionRepo->buscarPorPrestamoId(PrestamoId::fromString($this->id));

        return view('gestionprestamosrecepciones::curador.aprobar-verificacion', compact('prestamo', 'verificacion'));
    }
}
