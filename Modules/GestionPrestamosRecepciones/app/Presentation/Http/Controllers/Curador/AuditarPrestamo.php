<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico\ActualizarRecordatoriosPrestamoEspecificoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico\ActualizarRecordatoriosPrestamoEspecificoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Auditoría de préstamo'])]
final class AuditarPrestamo extends Component
{
    use HandlesDomainExceptions;

    public string $prestamoId;

    /** @var list<array{diasAntes: int, fecha: string}> */
    public array $recordatoriosPersonalizados = [];

    public bool $mostrarModalRecordatorios = false;

    /** @var list<int> */
    public array $diasAntesModal = [];

    public string $nuevoDiaModal = '';

    public function mount(string $id, RecordatorioDevolucionRepositoryInterface $repo): void
    {
        $this->prestamoId = $id;

        $prestamo = PrestamoEloquentModel::query()->find($id);

        if ($prestamo === null) {
            abort(404);
        }

        $this->cargarRecordatorios($repo);
    }

    public function abrirModalRecordatorios(): void
    {
        $this->diasAntesModal = count($this->recordatoriosPersonalizados) > 0
            ? array_column($this->recordatoriosPersonalizados, 'diasAntes')
            : [30, 15, 7, 1];

        $this->nuevoDiaModal = '';
        $this->mostrarModalRecordatorios = true;
    }

    public function toggleDiaModal(int $dia): void
    {
        if (in_array($dia, array_map('intval', $this->diasAntesModal), true)) {
            $this->diasAntesModal = array_values(
                array_filter($this->diasAntesModal, fn (mixed $d) => (int) $d !== $dia)
            );
        } else {
            $this->diasAntesModal[] = $dia;
            rsort($this->diasAntesModal);
        }
    }

    public function agregarDiaModal(): void
    {
        $dia = (int) $this->nuevoDiaModal;

        if ($dia < 1 || in_array($dia, array_map('intval', $this->diasAntesModal), true)) {
            $this->nuevoDiaModal = '';

            return;
        }

        $this->diasAntesModal[] = $dia;
        rsort($this->diasAntesModal);
        $this->nuevoDiaModal = '';
    }

    public function quitarDiaModal(int $dia): void
    {
        $this->diasAntesModal = array_values(
            array_filter($this->diasAntesModal, fn (mixed $d) => (int) $d !== $dia)
        );
    }

    public function actualizarRecordatorios(
        ActualizarRecordatoriosPrestamoEspecificoHandler $handler,
        RecordatorioDevolucionRepositoryInterface $repo,
    ): void {
        $this->validate([
            'diasAntesModal' => ['required', 'array', 'min:1'],
            'diasAntesModal.*' => ['integer', 'min:1'],
        ]);

        try {
            $handler->handle(new ActualizarRecordatoriosPrestamoEspecificoInput(
                prestamoId: $this->prestamoId,
                curadorId: (string) auth()->id(),
                diasAntes: array_values(array_map('intval', $this->diasAntesModal)),
            ));

            $this->cargarRecordatorios($repo);
            $this->mostrarModalRecordatorios = false;
        } catch (\Throwable $e) {
            $this->dispatch('domain-error', message: $e->getMessage());
        }
    }

    private function cargarRecordatorios(RecordatorioDevolucionRepositoryInterface $repo): void
    {
        $recordatorios = $repo->listarPorPrestamo(PrestamoId::fromString($this->prestamoId));

        $this->recordatoriosPersonalizados = array_map(
            fn ($r) => [
                'diasAntes' => $r->diasAntesVencimiento(),
                'fecha' => $r->fechaProgramada()->format('d/m/Y'),
            ],
            $recordatorios,
        );
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
