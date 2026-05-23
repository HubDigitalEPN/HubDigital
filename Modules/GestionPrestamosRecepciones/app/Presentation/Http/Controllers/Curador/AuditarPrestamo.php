<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Auditoría de préstamo'])]
final class AuditarPrestamo extends Component
{
    use HandlesDomainExceptions;

    public string $prestamoId;

    public function mount(string $id): void
    {
        $this->prestamoId = $id;

        $prestamo = PrestamoEloquentModel::query()->find($id);

        if ($prestamo === null) {
            abort(404);
        }
    }

    public function render(
        ConsultarHistorialSolicitudHandler $historialSolicitudHandler,
        ConsultarHistorialPrestamoHandler $historialPrestamoHandler,
    ): View {
        $prestamo = PrestamoEloquentModel::query()->with('acta')->findOrFail($this->prestamoId);
        $acta = $prestamo->acta;
        $solicitud = $acta
            ? SolicitudPrestamoModel::query()->with('items')->find($acta->solicitud_prestamo_id)
            : null;

        $eventosSolicitud = [];
        $eventosPrestamo = [];

        if ($solicitud !== null) {
            $historialSolicitud = $historialSolicitudHandler->handle(new ConsultarHistorialSolicitudInput(
                solicitudId: $solicitud->id,
                usuarioId: (string) auth()->id(),
            ));
            $tiposActa = ['ActaEnviada', 'ActaFirmadaSubida', 'ActaDevueltaPorFirmaInvalida', 'ActaValidada'];
            $eventosSolicitud = array_map(
                fn ($e) => ['evento' => $e, 'origen' => in_array($e->tipo, $tiposActa) ? 'acta' : 'solicitud'],
                $historialSolicitud->eventos,
            );
        }

        $historialPrestamo = $historialPrestamoHandler->handle(new ConsultarHistorialPrestamoInput(
            prestamoId: $this->prestamoId,
            usuarioId: (string) auth()->id(),
        ));
        $eventosPrestamo = array_map(
            fn ($e) => ['evento' => $e, 'origen' => 'prestamo'],
            $historialPrestamo->eventos,
        );

        $timeline = collect([...$eventosSolicitud, ...$eventosPrestamo])
            ->sortBy(fn (array $item) => $item['evento']->ocurridoEn->getTimestamp())
            ->values()
            ->all();

        return view('gestionprestamosrecepciones::curador.auditar-prestamo', compact('prestamo', 'acta', 'solicitud', 'timeline'));
    }
}
