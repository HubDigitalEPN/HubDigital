<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico\ActualizarRecordatoriosPrestamoEspecificoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarRecordatoriosPrestamoEspecifico\ActualizarRecordatoriosPrestamoEspecificoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialPrestamo\ConsultarHistorialPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional\HabilitarEnvioInternacionalHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\HabilitarEnvioInternacional\HabilitarEnvioInternacionalInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\RecordatorioDevolucionRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEntregaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

/**
 * Componente Livewire para la auditoría y gestión de detalles de un préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Auditoría de préstamo'])]
final class AuditarPrestamo extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    public string $prestamoId;

    public string $successMessage = '';

    #[Validate('required|file|mimes:pdf|max:10240')]
    public $documentoExportacion = null;

    /** @var list<array{diasAntes: int, fecha: string}> */
    public array $recordatoriosPersonalizados = [];

    public bool $mostrarModalRecordatorios = false;

    /** @var list<int> */
    public array $diasAntesModal = [];

    public string $nuevoDiaModal = '';

    /**
     * @param string $id
     * @param RecordatorioDevolucionRepositoryInterface $repo
     * @return void
     */
    public function mount(string $id, RecordatorioDevolucionRepositoryInterface $repo): void
    {
        $this->prestamoId = $id;

        $prestamo = PrestamoEloquentModel::query()->find($id);

        if ($prestamo === null) {
            abort(404);
        }

        $this->cargarRecordatorios($repo);
    }

    /**
     * @return void
     */
    public function abrirModalRecordatorios(): void
    {
        $this->diasAntesModal = count($this->recordatoriosPersonalizados) > 0
            ? array_column($this->recordatoriosPersonalizados, 'diasAntes')
            : [30, 15, 7, 1];

        $this->nuevoDiaModal = '';
        $this->mostrarModalRecordatorios = true;
    }

    /**
     * @param int $dia
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @param int $dia
     * @return void
     */
    public function quitarDiaModal(int $dia): void
    {
        $this->diasAntesModal = array_values(
            array_filter($this->diasAntesModal, fn (mixed $d) => (int) $d !== $dia)
        );
    }

    /**
     * @param ActualizarRecordatoriosPrestamoEspecificoHandler $handler
     * @param RecordatorioDevolucionRepositoryInterface $repo
     * @return void
     */
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

    /**
     * @param HabilitarEnvioInternacionalHandler $handler
     * @return void
     */
    public function habilitarEnvio(HabilitarEnvioInternacionalHandler $handler): void
    {
        $this->validate(['documentoExportacion' => 'required|file|mimes:pdf|max:10240']);

        $prestamoModel = PrestamoEloquentModel::findOrFail($this->prestamoId);
        $ruta = $this->documentoExportacion->store('exportaciones', 'public');

        $handler->handle(new HabilitarEnvioInternacionalInput(
            actaId: $prestamoModel->acta_prestamo_id,
            curadorId: (string) auth()->id(),
            documentoRuta: $ruta,
        ));

        $this->successMessage = 'Documento registrado. El préstamo pasa a en tránsito.';
        $this->documentoExportacion = null;
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

    /**
     * @param ConsultarHistorialSolicitudHandler $historialSolicitudHandler
     * @param ConsultarHistorialPrestamoHandler $historialPrestamoHandler
     * @param VerificacionEntregaPrestamoRepositoryInterface $verificacionRepo
     * @return View
     */
    public function render(
        ConsultarHistorialSolicitudHandler $historialSolicitudHandler,
        ConsultarHistorialPrestamoHandler $historialPrestamoHandler,
        VerificacionEntregaPrestamoRepositoryInterface $verificacionRepo,
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

        $verificacion = $verificacionRepo->buscarPorPrestamoId(PrestamoId::fromString($this->prestamoId));

        $nombreValidador = $acta?->validada_por
            ? (User::find($acta->validada_por)?->name ?? $acta->validada_por)
            : null;

        return view('gestionprestamosrecepciones::curador.auditar-prestamo', compact('prestamo', 'acta', 'solicitud', 'timeline', 'verificacion', 'nombreValidador'));
    }
}
