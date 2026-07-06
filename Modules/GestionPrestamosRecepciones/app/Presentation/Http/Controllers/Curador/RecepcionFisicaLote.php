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
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemChecklistRecepcion;

/**
 * Componente Livewire para que el curador ejecute la recepción física del lote de una
 * solicitud aprobada documentalmente: verifica la lista de ítems y decide aprobar,
 * suspender (anomalía subsanable) o aceptar con observaciones.
 */
#[Layout('layouts.app', params: ['title' => 'Recepción física del lote'])]
final class RecepcionFisicaLote extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public string $nombreInvestigador = '';

    /** @var array<int, bool> Conformidad por índice de ITEMS; nacen en "No conforme" y el curador las activa. */
    public array $conforme = [0 => false, 1 => false, 2 => false, 3 => false];

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
        IniciarRecepcionLoteHandler $iniciar,
        UsuarioNombrePort $usuarios,
    ): void {
        $this->id = $id;

        $recepcion = $detalle->handle(new ConsultarDetalleRecepcionInput($id));
        abort_if($recepcion === null, 404);

        // El curador abre la recepción física del lote (idempotente).
        ($iniciar)(new IniciarRecepcionLoteInput(
            solicitudId: $id,
            curadorId: (string) auth()->id(),
        ));

        $this->nombreInvestigador = $usuarios->obtenerNombre($recepcion->investigadorId) ?? $recepcion->investigadorId;
    }

    public function aprobar(AprobarRecepcionLoteHandler $handler): void
    {
        // Si algún ítem no está conforme, la aprobación pasa a ser "con observaciones":
        // se pide el tipo de anomalía en el modal y se resuelve por aceptarConObservaciones().
        if (in_array(false, $this->conforme, true)) {
            $this->showObservacionModal = true;

            return;
        }

        $items = array_map(
            fn (ItemChecklistRecepcion $item): array => ['item' => $item->value, 'resultado' => $item->resultadoConforme()],
            ItemChecklistRecepcion::cases(),
        );

        ($handler)(new AprobarRecepcionLoteInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
            itemsVerificacion: $items,
        ));

        $this->dispatch('toast', message: 'Recepción aprobada. Acta Digital de Recepción emitida.');
    }

    public function rechazar(RechazarRecepcionLoteHandler $handler): void
    {
        $this->validateOnly('motivoFallo');

        ($handler)(new RechazarRecepcionLoteInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
            motivoFallo: $this->motivoFallo,
        ));

        $this->showRechazoModal = false;
        $this->dispatch('toast', message: 'Recepción suspendida. Se emitió la orden de acción correctiva.');
    }

    public function aceptarConObservaciones(AceptarRecepcionConObservacionesHandler $handler): void
    {
        $itemsNoConformes = [];
        foreach (ItemChecklistRecepcion::cases() as $indice => $item) {
            if (! ($this->conforme[$indice] ?? false)) {
                $itemsNoConformes[] = $item->value;
            }
        }

        if ($itemsNoConformes === []) {
            $this->showObservacionModal = false;

            return;
        }

        ($handler)(new AceptarRecepcionConObservacionesInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
            itemsNoConformes: $itemsNoConformes,
            comentario: trim($this->comentarioObservacion) !== '' ? trim($this->comentarioObservacion) : null,
        ));

        $this->showObservacionModal = false;
        $this->dispatch('toast', message: 'Recepción aceptada con observaciones. Acta Digital de Recepción emitida.');
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
