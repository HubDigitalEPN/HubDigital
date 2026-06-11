<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

/**
 * Componente Livewire para el detalle de una solicitud.
 */
#[Layout('layouts.app', params: ['title' => 'Detalle de Solicitud'])]
final class DetalleSolicitud extends Component
{
    public string $id;

    public ?SolicitudPrestamoModel $solicitud = null;

    /**
     * Inicializa el componente.
     *
     * @param string $id
     * @return void
     */
    public function mount(string $id): void
    {
        $this->id = $id;
        $this->solicitud = SolicitudPrestamoModel::query()->with('items')->find($id);

        if (! $this->solicitud) {
            return;
        }

        if ($this->solicitud->investigador_id !== (string) auth()->id()) {
            abort(403);
        }
    }

    /**
     * Renderiza el componente.
     *
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler $historialHandler
     * @return \Illuminate\View\View
     */
    public function render(ConsultarHistorialSolicitudHandler $historialHandler): View
    {
        $historial = $historialHandler->handle(new ConsultarHistorialSolicitudInput(
            solicitudId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        return view('gestionprestamosrecepciones::investigador.detalle-solicitud', compact('historial'));
    }
}
