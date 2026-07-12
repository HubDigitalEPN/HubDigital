<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Componente Livewire para el detalle de un depósito.
 */
#[Layout('layouts.app', params: ['title' => 'Detalle de Depósito'])]
final class DetalleDeposito extends Component
{
    public string $id;

    /**
     * Inicializa el componente.
     */
    public function mount(string $id): void
    {
        $this->id = $id;

        $deposito = SolicitudDepositoEloquentModel::find($id);

        if ($deposito && $deposito->investigador_id !== (string) auth()->id()) {
            abort(403);
        }
    }

    /**
     * Renderiza el componente.
     */
    public function render(
        MatrizEspeciesRepositoryInterface $matrizRepo,
        ConsultarDetalleRecepcionHandler $recepcionHandler,
    ): View {
        $deposito = SolicitudDepositoEloquentModel::find($this->id);
        $matriz = $deposito ? $matrizRepo->buscarPorSolicitudId($this->id) : null;
        // Estado de la recepción física (para exponer la descarga del Acta de Recepción).
        $recepcion = $deposito ? $recepcionHandler->handle(new ConsultarDetalleRecepcionInput($this->id)) : null;

        return view('gestionprestamosrecepciones::investigador.detalle-deposito', compact('deposito', 'matriz', 'recepcion'));
    }
}
