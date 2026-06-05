<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

#[Layout('layouts.app', params: ['title' => 'Detalle de Depósito'])]
final class DetalleDeposito extends Component
{
    public string $id;

    public function mount(string $id): void
    {
        $this->id = $id;

        $deposito = SolicitudDepositoEloquentModel::find($id);

        if ($deposito && $deposito->investigador_id !== (string) auth()->id()) {
            abort(403);
        }
    }

    public function render(MatrizEspeciesRepositoryInterface $matrizRepo): View
    {
        $deposito = SolicitudDepositoEloquentModel::find($this->id);
        $matriz = $deposito ? $matrizRepo->buscarPorSolicitudId($this->id) : null;

        return view('gestionprestamosrecepciones::investigador.detalle-deposito', compact('deposito', 'matriz'));
    }
}
