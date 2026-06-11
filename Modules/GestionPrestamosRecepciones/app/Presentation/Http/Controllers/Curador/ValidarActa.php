<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud\ConsultarHistorialSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar\DevolverActaParaRefirmarHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DevolverActaParaRefirmar\DevolverActaParaRefirmarInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarActaFirmada\ValidarActaFirmadaInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

/**
 * Componente Livewire para la validación de actas firmadas por el investigador.
 */
#[Layout('layouts.app', params: ['title' => 'Validar acta firmada'])]
final class ValidarActa extends Component
{
    use HandlesDomainExceptions;

    public string $id;

    public ?ActaPrestamoModel $acta = null;

    public string $successMessage = '';

    public bool $showMotivoModal = false;

    #[Validate('required|string|min:10')]
    public string $motivoDevolucion = '';

    /**
     * @param string $id
     * @return void
     */
    public function mount(string $id): void
    {
        $this->id = $id;
        $this->acta = ActaPrestamoModel::query()->with('solicitud')->findOrFail($id);
    }

    /**
     * @param ValidarActaFirmadaHandler $handler
     * @return void
     */
    public function validar(ValidarActaFirmadaHandler $handler): void
    {
        $handler->handle(new ValidarActaFirmadaInput(
            actaId: $this->id,
            curadorId: (string) auth()->id(),
        ));

        $this->successMessage = 'Acta validada. Los especímenes están siendo coordinados para el despacho al investigador. El préstamo se activará una vez que el investigador confirme la recepción.';
        $this->acta = ActaPrestamoModel::query()->with('solicitud')->find($this->id);
    }

    /**
     * @param DevolverActaParaRefirmarHandler $handler
     * @return void
     */
    public function devolverParaRefirmar(DevolverActaParaRefirmarHandler $handler): void
    {
        $this->validate(['motivoDevolucion' => 'required|string|min:10']);

        $handler->handle(new DevolverActaParaRefirmarInput(
            actaId: $this->id,
            curadorId: (string) auth()->id(),
            motivo: $this->motivoDevolucion,
        ));

        $this->redirectRoute('prestamos.curador.actas', navigate: true);
    }

    private static array $eventosActa = [
        'ActaEnviada',
        'ActaFirmadaSubida',
        'ActaFirmadaDigitalmente',
        'ActaDevueltaPorFirmaInvalida',
        'ActaValidada',
    ];

    /**
     * @param ConsultarHistorialSolicitudHandler $historialHandler
     * @return View
     */
    public function render(ConsultarHistorialSolicitudHandler $historialHandler): View
    {
        $prestamo = PrestamoEloquentModel::where('acta_prestamo_id', $this->id)->first();

        $historialActa = [];

        if ($this->acta !== null) {
            $historial = $historialHandler->handle(new ConsultarHistorialSolicitudInput(
                solicitudId: $this->acta->solicitud_prestamo_id,
                usuarioId: (string) auth()->id(),
            ));

            $historialActa = array_values(
                array_filter(
                    $historial->eventos,
                    fn ($e) => in_array($e->tipo, self::$eventosActa, true),
                )
            );
        }

        return view('gestionprestamosrecepciones::curador.validar-acta', compact('prestamo', 'historialActa'));
    }
}
