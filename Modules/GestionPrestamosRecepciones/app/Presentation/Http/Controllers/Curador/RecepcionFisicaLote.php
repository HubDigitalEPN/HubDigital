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

/**
 * Componente Livewire para que el curador ejecute la recepción física del lote de una
 * solicitud aprobada documentalmente: verifica la lista de ítems y decide aprobar,
 * suspender (anomalía subsanable) o aceptar con observaciones.
 */
#[Layout('layouts.app', params: ['title' => 'Recepción física del lote'])]
final class RecepcionFisicaLote extends Component
{
    use HandlesDomainExceptions;

    /** Ítems de la lista de verificación de recepción y su resultado cuando son conformes. */
    private const ITEMS = [
        ['item' => 'Nivel de alcohol', 'conforme' => 'Adecuado'],
        ['item' => 'Estado de los especímenes', 'conforme' => 'Sanos'],
        ['item' => 'Integridad de los frascos', 'conforme' => 'Íntegros'],
        ['item' => 'Completitud del conteo', 'conforme' => 'Exacto'],
    ];

    public string $id;

    public string $nombreInvestigador = '';

    /** @var array<int, bool> Conformidad por índice de ITEMS; nacen en "No conforme" y el curador las activa. */
    public array $conforme = [0 => false, 1 => false, 2 => false, 3 => false];

    // ── Modal: suspender por anomalía subsanable ─────────────────────────────
    public bool $showRechazoModal = false;

    #[Validate('required')]
    public string $motivoFallo = '';

    // ── Modal: aceptar con observaciones (anomalía no devolvible) ─────────────
    public bool $showObservacionModal = false;

    #[Validate('required')]
    public string $tipoObservacion = '';

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
        if (in_array(false, $this->conforme, true)) {
            $this->addError('conforme', 'Marca todos los ítems como conformes, o usa Suspender / Aceptar con observaciones.');

            return;
        }

        $items = array_map(
            fn (array $definicion): array => ['item' => $definicion['item'], 'resultado' => $definicion['conforme']],
            self::ITEMS,
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
        $this->validateOnly('tipoObservacion');

        ($handler)(new AceptarRecepcionConObservacionesInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
            tipoObservacion: $this->tipoObservacion,
        ));

        $this->showObservacionModal = false;
        $this->dispatch('toast', message: 'Recepción aceptada con observaciones. Acta Digital de Recepción emitida.');
    }

    public function render(ConsultarDetalleRecepcionHandler $detalle): View
    {
        $recepcion = $detalle->handle(new ConsultarDetalleRecepcionInput($this->id));
        abort_if($recepcion === null, 404);

        return view('gestionprestamosrecepciones::curador.recepcion-fisica-lote', [
            'recepcion' => $recepcion,
            'items' => self::ITEMS,
        ]);
    }
}
