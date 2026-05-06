<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\SolicitudPrestamoModel;

#[Layout('layouts.app', params: ['title' => 'Solicitud de Préstamo'])]
final class SolicitudForm extends Component
{
    use HandlesDomainExceptions;

    public ?string $solicitudId = null;

    #[Validate('required|string|max:255')]
    public string $tituloEstudio = '';

    #[Validate('required|string|max:255')]
    public string $institucionAdscripcion = '';

    #[Validate('required|string|max:255')]
    public string $lineaInvestigacion = '';

    #[Validate('required|string')]
    public string $propositoPrestamo = '';

    #[Validate('required|integer|min:1|max:12')]
    public int $duracionPropuestaMeses = 1;

    /** @var list<array{especimen_codigo_externo: string, cantidad_solicitada: int}> */
    public array $items = [];

    public string $successMessage = '';

    public function mount(?string $id = null): void
    {
        $this->solicitudId = $id;

        if ($id !== null) {
            $solicitud = SolicitudPrestamoModel::query()->with('items')->findOrFail($id);

            if ($solicitud->investigador_id !== (string) auth()->id()) {
                abort(403);
            }

            $this->tituloEstudio = $solicitud->titulo_estudio ?? '';
            $this->institucionAdscripcion = $solicitud->institucion_adscripcion ?? '';
            $this->lineaInvestigacion = $solicitud->linea_investigacion ?? '';
            $this->propositoPrestamo = $solicitud->proposito_prestamo ?? '';
            $this->duracionPropuestaMeses = $solicitud->duracion_propuesta_meses ?? 1;
            $this->items = $solicitud->items
                ->map(fn ($item) => [
                    'especimen_codigo_externo' => $item->especimen_codigo_externo,
                    'cantidad_solicitada' => $item->cantidad_solicitada,
                ])
                ->values()
                ->toArray();
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['especimen_codigo_externo' => '', 'cantidad_solicitada' => 1];
    }

    public function removeItem(int $index): void
    {
        array_splice($this->items, $index, 1);
        $this->items = array_values($this->items);
    }

    public function guardarBorrador(
        RegistrarSolicitudPrestamoHandler $registrar,
        ActualizarSolicitudPrestamoHandler $actualizar,
    ): void {
        $this->validate([
            'tituloEstudio' => 'required|string|max:255',
            'institucionAdscripcion' => 'required|string|max:255',
            'lineaInvestigacion' => 'required|string|max:255',
            'propositoPrestamo' => 'required|string',
            'duracionPropuestaMeses' => 'required|integer|min:1|max:12',
            'items' => 'required|array|min:1',
            'items.*.especimen_codigo_externo' => 'required|string',
            'items.*.cantidad_solicitada' => 'required|integer|min:1',
        ]);

        $investigadorId = (string) auth()->id();

        if ($this->solicitudId === null) {
            $output = $registrar->handle(new RegistrarSolicitudPrestamoInput(
                investigadorId: $investigadorId,
                tituloEstudio: $this->tituloEstudio,
                institucionAdscripcion: $this->institucionAdscripcion,
                lineaInvestigacion: $this->lineaInvestigacion,
                propositoPrestamo: $this->propositoPrestamo,
                duracionPropuestaMeses: $this->duracionPropuestaMeses,
                items: $this->items,
            ));
            $this->solicitudId = $output->solicitudId;
        } else {
            $actualizar->handle(new ActualizarSolicitudPrestamoInput(
                solicitudId: $this->solicitudId,
                investigadorId: $investigadorId,
                tituloEstudio: $this->tituloEstudio,
                institucionAdscripcion: $this->institucionAdscripcion,
                lineaInvestigacion: $this->lineaInvestigacion,
                propositoPrestamo: $this->propositoPrestamo,
                duracionPropuestaMeses: $this->duracionPropuestaMeses,
                items: $this->items,
                justificacionExtendida: null,
            ));
        }

        $this->successMessage = 'Borrador guardado correctamente.';
    }

    public function enviarSolicitud(EnviarSolicitudPrestamoHandler $handler): void
    {
        if ($this->solicitudId === null) {
            $this->addError('solicitudId', 'Primero guarda el borrador antes de enviar.');

            return;
        }

        $handler->handle(new EnviarSolicitudPrestamoInput(
            solicitudId: $this->solicitudId,
            investigadorId: (string) auth()->id(),
        ));

        $this->redirectRoute('prestamos.investigador.solicitud.detalle', ['id' => $this->solicitudId], navigate: true);
    }

    public function render(): View
    {
        return view('gestionprestamosrecepciones::investigador.solicitud-form');
    }
}
