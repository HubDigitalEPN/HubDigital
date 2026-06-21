<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleSolicitud\ConsultarDetalleSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleSolicitud\ConsultarDetalleSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;

/**
 * Componente Livewire para el detalle de una solicitud.
 */
#[Layout('layouts.app', params: ['title' => 'Detalle de Solicitud'])]
final class DetalleSolicitud extends Component
{
    public string $id;

    /**
     * Inicializa el componente.
     *
     * @param string $id
     * @param ConsultarDetalleSolicitudHandler $handler
     * @return void
     */
    public function mount(string $id, ConsultarDetalleSolicitudHandler $handler): void
    {
        $this->id = $id;

        $solicitud = $handler->handle(new ConsultarDetalleSolicitudInput(solicitudId: $id));

        if ($solicitud === null) {
            return;
        }

        if ($solicitud->investigadorId !== (string) auth()->id()) {
            abort(403);
        }
    }

    /**
     * Renderiza el componente.
     *
     * @param ConsultarDetalleSolicitudHandler $detalleHandler
     * @param ConsultarHistorialSolicitudHandler $historialHandler
     * @return \Illuminate\View\View
     */
    public function render(
        ConsultarDetalleSolicitudHandler $detalleHandler,
        ConsultarHistorialSolicitudHandler $historialHandler,
    ): View {
        $solicitud = $detalleHandler->handle(new ConsultarDetalleSolicitudInput(solicitudId: $this->id));

        $historial = $historialHandler->handle(new ConsultarHistorialSolicitudInput(
            solicitudId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        return view('gestionprestamosrecepciones::investigador.detalle-solicitud', compact('solicitud', 'historial'));
    }
}
