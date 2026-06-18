<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEspecimenesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

/**
 * Componente Livewire para el detalle de un préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Detalle del Préstamo'])]
final class DetallePrestamo extends Component
{
    public string $id;

    /**
     * Inicializa el componente.
     *
     * @param string $id
     * @return void
     */
    public function mount(string $id): void
    {
        $this->id = $id;

        $prestamo = PrestamoEloquentModel::query()->with('acta.solicitud')->find($id);

        if ($prestamo === null) {
            abort(404);
        }

        if ($prestamo->acta?->solicitud?->investigador_id !== (string) auth()->id()) {
            abort(403);
        }
    }

    public string $successMessage = '';

    /**
     * Renderiza el componente.
     *
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler $historialSolicitudHandler
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler $historialPrestamoHandler
     * @param \Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface $recordatorioRepo
     * @param \Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEspecimenesRepositoryInterface $verificacionRepo
     * @return \Illuminate\View\View
     */
    public function render(
        ConsultarHistorialSolicitudHandler $historialSolicitudHandler,
        ConsultarHistorialPrestamoHandler $historialPrestamoHandler,
        RecordatorioDevolucionRepositoryInterface $recordatorioRepo,
        VerificacionEspecimenesRepositoryInterface $verificacionRepo,
    ): View {
        $prestamo = PrestamoEloquentModel::query()->with('acta')->findOrFail($this->id);
        $acta = $prestamo->acta;
        $solicitud = $acta
            ? SolicitudPrestamoModel::query()->with('items')->find($acta->solicitud_prestamo_id)
            : null;

        $tiposActa = ['ActaEnviada', 'ActaFirmadaSubida', 'ActaDevueltaPorFirmaInvalida', 'ActaValidada'];

        $eventosSolicitud = [];
        if ($solicitud !== null) {
            $historialSolicitud = $historialSolicitudHandler->handle(new ConsultarHistorialSolicitudInput(
                solicitudId: $solicitud->id,
                usuarioId: (string) auth()->id(),
            ));
            $eventosSolicitud = array_map(
                fn ($e) => ['evento' => $e, 'origen' => in_array($e->tipo, $tiposActa) ? 'acta' : 'solicitud'],
                $historialSolicitud->eventos,
            );
        }

        $historialPrestamo = $historialPrestamoHandler->handle(new ConsultarHistorialPrestamoInput(
            prestamoId: $this->id,
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

        $recordatorios = array_map(
            fn ($r) => [
                'diasAntes' => $r->diasAntesVencimiento(),
                'fecha' => $r->fechaProgramada()->format('d/m/Y'),
            ],
            $recordatorioRepo->listarPorPrestamo(PrestamoId::fromString($this->id)),
        );

        $prestamoId = PrestamoId::fromString($this->id);
        $verificacion = $verificacionRepo->buscarPorPrestamoYTipo($prestamoId, TipoVerificacion::Recepcion);
        $verificacionCierre = in_array($prestamo->estado, ['cerrado', 'cerrado_con_observacion'], true)
            ? $verificacionRepo->buscarPorPrestamoYTipo($prestamoId, TipoVerificacion::Devolucion)
            : null;

        return view('gestionprestamosrecepciones::investigador.detalle-prestamo', compact('prestamo', 'acta', 'solicitud', 'timeline', 'recordatorios', 'verificacion', 'verificacionCierre'));
    }
}
