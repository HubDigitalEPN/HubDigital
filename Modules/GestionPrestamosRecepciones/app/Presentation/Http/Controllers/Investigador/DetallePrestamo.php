<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetallePrestamo\ConsultarDetallePrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetallePrestamo\ConsultarDetallePrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes\ConsultarVerificacionEspecimenesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes\ConsultarVerificacionEspecimenesInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoVerificacion;

/**
 * Componente Livewire para el detalle de un préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Detalle del Préstamo'])]
final class DetallePrestamo extends Component
{
    public string $id;

    public string $successMessage = '';

    /**
     * Inicializa el componente.
     *
     * @param string $id
     * @param ConsultarDetallePrestamoHandler $handler
     * @return void
     */
    public function mount(string $id, ConsultarDetallePrestamoHandler $handler): void
    {
        $this->id = $id;

        $detalle = $handler->handle(new ConsultarDetallePrestamoInput(prestamoId: $id));

        if ($detalle === null) {
            abort(404);
        }

        if ($detalle->investigadorId !== (string) auth()->id()) {
            abort(403);
        }
    }

    /**
     * Renderiza el componente.
     *
     * @param ConsultarDetallePrestamoHandler $detalleHandler
     * @param ConsultarHistorialSolicitudHandler $historialSolicitudHandler
     * @param ConsultarHistorialPrestamoHandler $historialPrestamoHandler
     * @param RecordatorioDevolucionRepositoryInterface $recordatorioRepo
     * @param ConsultarVerificacionEspecimenesHandler $verificacionHandler
     * @return View
     */
    public function render(
        ConsultarDetallePrestamoHandler $detalleHandler,
        ConsultarHistorialSolicitudHandler $historialSolicitudHandler,
        ConsultarHistorialPrestamoHandler $historialPrestamoHandler,
        RecordatorioDevolucionRepositoryInterface $recordatorioRepo,
        ConsultarVerificacionEspecimenesHandler $verificacionHandler,
    ): View {
        $detalle = $detalleHandler->handle(new ConsultarDetallePrestamoInput(prestamoId: $this->id));

        if ($detalle === null) {
            abort(404);
        }

        $tiposActa = ['ActaEnviada', 'ActaFirmadaSubida', 'ActaDevueltaPorFirmaInvalida', 'ActaValidada'];

        $eventosSolicitud = [];
        if ($detalle->solicitudId !== null) {
            $historialSolicitud = $historialSolicitudHandler->handle(new ConsultarHistorialSolicitudInput(
                solicitudId: $detalle->solicitudId,
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

        $verificacion = $verificacionHandler->handle(new ConsultarVerificacionEspecimenesInput(
            prestamoId: $this->id,
            tipo: TipoVerificacion::Recepcion,
        ));
        $verificacionCierre = in_array($detalle->estadoPrestamo->value, ['cerrado', 'cerrado_con_observacion'], true)
            ? $verificacionHandler->handle(new ConsultarVerificacionEspecimenesInput(
                prestamoId: $this->id,
                tipo: TipoVerificacion::Devolucion,
            ))
            : null;

        return view('gestionprestamosrecepciones::investigador.detalle-prestamo', compact('detalle', 'timeline', 'recordatorios', 'verificacion', 'verificacionCierre'));
    }
}
