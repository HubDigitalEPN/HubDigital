<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActa\ConsultarActaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActa\ConsultarActaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar\DevolverActaParaRefirmarHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar\DevolverActaParaRefirmarInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaInput;

/**
 * Componente Livewire para la validación de actas firmadas por el investigador.
 */
#[Layout('layouts.app', params: ['title' => 'Validar acta firmada'])]
final class ValidarActa extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public string $successMessage = '';

    public bool $showMotivoModal = false;

    public bool $showValidarFirmaModal = false;

    #[Validate('required|string|min:10')]
    public string $motivoDevolucion = '';

    public bool $devolverActa = true;

    public bool $devolverIdentidad = true;

    public function mount(string $id, ConsultarActaHandler $handler): void
    {
        $this->id = $id;

        if ($handler->handle(new ConsultarActaInput(actaId: $id)) === null) {
            abort(404);
        }
    }

    public function validar(ValidarActaFirmadaHandler $handler): void
    {
        $handler->handle(new ValidarActaFirmadaInput(
            actaId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->showValidarFirmaModal = false;
        $this->successMessage = 'Acta validada. Los especímenes están siendo coordinados para el despacho al investigador. El préstamo se activará una vez que el investigador confirme la recepción.';
    }

    public function devolverParaRefirmar(DevolverActaParaRefirmarHandler $handler): void
    {
        $this->validate(['motivoDevolucion' => 'required|string|min:10']);

        if (! $this->devolverActa && ! $this->devolverIdentidad) {
            $this->addError('devolverActa', 'Selecciona al menos un documento para devolver.');

            return;
        }

        $handler->handle(new DevolverActaParaRefirmarInput(
            actaId: $this->id,
            curadorId: (string) auth()->id(),
            motivo: $this->motivoDevolucion,
            devolverActa: $this->devolverActa,
            devolverIdentidad: $this->devolverIdentidad,
        ));

        $this->redirectRoute('prestamos.curador.actas', navigate: true);
    }

    private static array $eventosActa = [
        'ActaEnviada',
        'ActaFirmadaSubida',
        'ActaFirmadaDigitalmente',
        'ActaDevueltaPorFirmaInvalida',
        'ActaValidada',
        'ActaFirmadaCriptograficamentePorCurador',
    ];

    public function render(
        ConsultarActaHandler $actaHandler,
        ConsultarHistorialSolicitudHandler $historialHandler,
    ): View {
        $acta = $actaHandler->handle(new ConsultarActaInput(actaId: $this->id));

        if ($acta === null) {
            abort(404);
        }

        $historial = $historialHandler->handle(new ConsultarHistorialSolicitudInput(
            solicitudId: $acta->solicitudPrestamoId,
            usuarioId: (string) auth()->id(),
        ));

        $historialActa = array_values(
            array_filter(
                $historial->eventos,
                fn ($e) => in_array($e->tipo, self::$eventosActa, true),
            )
        );

        return view('gestionprestamosrecepciones::curador.validar-acta', compact('acta', 'historialActa'));
    }
}
