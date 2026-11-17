<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones\AceptarRecepcionConObservacionesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones\AceptarRecepcionConObservacionesInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarRecepcionLote\RechazarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarRecepcionLote\RechazarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ReintentarRecepcionLote\ReintentarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ReintentarRecepcionLote\ReintentarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemChecklistRecepcion;

/**
 * Componente Livewire para que el receptor EPN ejecute la recepción física del lote de una
 * solicitud aprobada documentalmente: verifica la lista de ítems y decide aprobar,
 * suspender (anomalía subsanable) o aceptar con observaciones.
 */
#[Layout('layouts.app', params: ['title' => 'Recepción física del lote'])]
final class RecepcionFisicaLote extends Component
{
    use HandlesDomainExceptions;

    public string $id;


    public string $nombreInvestigador = '';

    /**
     * @var array<int, 'pendiente'|'conforme'|'no_conforme'>
     *
     * Cada criterio empieza pendiente: no comprobarlo todavía no equivale a declarar
     * una no conformidad.
     */
    public array $estadoCriterio = [
        0 => 'pendiente',
        1 => 'pendiente',
        2 => 'pendiente',
        3 => 'pendiente',
    ];

    // ── Modal: suspender por anomalía subsanable ─────────────────────────────
    public bool $showRechazoModal = false;

    #[Validate('required')]
    public string $motivoFallo = '';

    // ── Modal: aceptar con observaciones (ítems no conformes del checklist) ───
    public bool $showObservacionModal = false;

    /** Comentario libre opcional que complementa a los ítems no conformes. */
    public string $comentarioObservacion = '';

    public function mount(
        string $id,
        ConsultarDetalleRecepcionHandler $detalle,
        UsuarioNombrePort $usuarios,
    ): void {
        $this->id = $id;
        $solicitud = \Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel::findOrFail($id);
        if ($solicitud->recepcion_qr_en) {
            $this->redirectRoute('depositos.ingreso.contenido', $id, navigate: true);
            return;
        }

        if (!\Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel::where('solicitud_deposito_id', $id)->exists()) {
            $this->redirect(url(app(\Modules\GestionPrestamosRecepciones\Infrastructure\Services\DocumentoIngresoDeposito::class)->urlQr($solicitud)));
            return;
        }

        $recepcion = $detalle->handle(new ConsultarDetalleRecepcionInput($id));
        abort_if($recepcion === null, 404);

        $this->nombreInvestigador = $usuarios->obtenerNombre($recepcion->investigadorId) ?? $recepcion->investigadorId;
    }

    /** Registra explícitamente el inicio material de la constatación. */
    public function iniciarRecepcion(IniciarRecepcionLoteHandler $iniciar): void
    {
        ($iniciar)(new IniciarRecepcionLoteInput(
            solicitudId: $this->id,
            receptorId: (string) auth()->id(),
        ));

        $this->dispatch('toast', message: 'Constatación iniciada. Completa la lista de verificación del lote físico.');
    }

    public function aprobar(AprobarRecepcionLoteHandler $handler): void
    {
        $this->exigirFlujoHistorico();
        if ($this->tieneCriteriosPendientesOInvalidos()) {
            $this->addError('estadoCriterio', 'Comprueba cada criterio antes de confirmar la recepción.');

            return;
        }

        // Si algún ítem se declaró expresamente no conforme, la aprobación pasa a
        // ser "con observaciones" y se resuelve por aceptarConObservaciones().
        if (in_array('no_conforme', $this->estadoCriterio, true)) {
            $this->showObservacionModal = true;

            return;
        }

        $items = array_map(
            fn (ItemChecklistRecepcion $item): array => ['item' => $item->value, 'resultado' => $item->resultadoConforme()],
            ItemChecklistRecepcion::cases(),
        );

        ($handler)(new AprobarRecepcionLoteInput(
            solicitudId: $this->id,
            receptorId: (string) auth()->id(),
            itemsVerificacion: $items,
        ));

        $this->dispatch('toast', message: 'Lote recibido y constatado. Curaduría ya puede generar el acta final.');
    }

    public function rechazar(RechazarRecepcionLoteHandler $handler): void
    {
        $this->validateOnly('motivoFallo');

        ($handler)(new RechazarRecepcionLoteInput(
            solicitudId: $this->id,
            receptorId: (string) auth()->id(),
            motivoFallo: $this->motivoFallo,
        ));

        $this->showRechazoModal = false;
        $this->dispatch('toast', message: 'Recepción suspendida. Se emitió la orden de acción correctiva.');
    }

    /**
     * Reabre la verificación de un lote suspendido para volver a evaluarlo con el mismo
     * Código QR, una vez subsanada la anomalía.
     */
    public function reintentar(ReintentarRecepcionLoteHandler $handler): void
    {
        ($handler)(new ReintentarRecepcionLoteInput(
            solicitudId: $this->id,
            receptorId: (string) auth()->id(),
        ));

        $this->estadoCriterio = [
            0 => 'pendiente',
            1 => 'pendiente',
            2 => 'pendiente',
            3 => 'pendiente',
        ];
        $this->motivoFallo = '';
        $this->dispatch('toast', message: 'Recepción reabierta. Vuelve a verificar el lote.');
    }

    public function aceptarConObservaciones(AceptarRecepcionConObservacionesHandler $handler): void
    {
        $this->exigirFlujoHistorico();
        if ($this->tieneCriteriosPendientesOInvalidos()) {
            $this->showObservacionModal = false;
            $this->addError('estadoCriterio', 'Comprueba cada criterio antes de confirmar la recepción.');

            return;
        }

        $itemsNoConformes = [];
        foreach (ItemChecklistRecepcion::cases() as $indice => $item) {
            if (($this->estadoCriterio[$indice] ?? 'pendiente') === 'no_conforme') {
                $itemsNoConformes[] = $item->value;
            }
        }

        if ($itemsNoConformes === []) {
            $this->showObservacionModal = false;

            return;
        }

        ($handler)(new AceptarRecepcionConObservacionesInput(
            solicitudId: $this->id,
            receptorId: (string) auth()->id(),
            itemsNoConformes: $itemsNoConformes,
            comentario: trim($this->comentarioObservacion) !== '' ? trim($this->comentarioObservacion) : null,
        ));

        $this->showObservacionModal = false;
        $this->dispatch('toast', message: 'Lote recibido y constatado con observaciones. Curaduría ya puede generar el acta final.');
    }

    private function exigirFlujoHistorico(): void
    {
        abort_if(\Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel::findOrFail($this->id)->recepcion_qr_en, 409, 'Registra y valida el contenido en la nueva pantalla de recepción.');
    }

    private function tieneCriteriosPendientesOInvalidos(): bool
    {
        foreach (array_keys(ItemChecklistRecepcion::cases()) as $indice) {
            if (! in_array($this->estadoCriterio[$indice] ?? null, ['conforme', 'no_conforme'], true)) {
                return true;
            }
        }

        return false;
    }

    public function render(ConsultarDetalleRecepcionHandler $detalle): View
    {
        $recepcion = $detalle->handle(new ConsultarDetalleRecepcionInput($this->id));
        abort_if($recepcion === null, 404);

        $items = array_map(
            fn (ItemChecklistRecepcion $item): array => ['item' => $item->value, 'resultado' => $item->resultadoConforme()],
            ItemChecklistRecepcion::cases(),
        );

        return view('gestionprestamosrecepciones::curador.recepcion-fisica-lote', [
            'recepcion' => $recepcion,
            'items' => $items,
        ]);
    }
}
