<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Mis Solicitudes de Préstamo'])]
final class MisSolicitudes extends Component
{
    use HandlesDomainExceptions;

    public function enviarSolicitud(string $id, EnviarSolicitudPrestamoHandler $handler): void
    {
        $handler->handle(new EnviarSolicitudPrestamoInput(
            solicitudId: $id,
            investigadorId: (string) auth()->id(),
        ));

        $this->redirectRoute('prestamos.investigador.solicitud.detalle', ['id' => $id], navigate: true);
    }

    public function render(): View
    {
        $solicitudes = SolicitudPrestamoModel::query()
            ->where('investigador_id', (string) auth()->id())
            ->orderByDesc('created_at')
            ->get();

        $actasPorSolicitud = ActaPrestamoModel::query()
            ->whereIn('solicitud_prestamo_id', $solicitudes->pluck('id'))
            ->get()
            ->keyBy('solicitud_prestamo_id');

        return view('gestionprestamosrecepciones::investigador.mis-solicitudes', compact('solicitudes', 'actasPorSolicitud'));
    }
}
