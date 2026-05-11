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

#[Layout('layouts.app', params: ['title' => 'Solicitud de préstamo'])]
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

    #[Validate('required|integer|min:1|max:24')]
    public int $duracionPropuestaMeses = 1;

    public string $justificacionExtendida = '';

    public string $comentarioCurador = '';

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
            $this->justificacionExtendida = $solicitud->justificacion_extendida ?? '';
            $this->comentarioCurador = $solicitud->comentario_curador ?? '';
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
            'duracionPropuestaMeses' => 'required|integer|min:1|max:24',
            'justificacionExtendida' => $this->duracionPropuestaMeses > 12 ? 'required|string|min:20' : 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.especimen_codigo_externo' => 'required|string',
            'items.*.cantidad_solicitada' => 'required|integer|min:1',
        ], [
            'tituloEstudio.required' => 'El título del estudio es obligatorio.',
            'tituloEstudio.max' => 'El título no puede superar los 255 caracteres.',
            'institucionAdscripcion.required' => 'La institución de adscripción es obligatoria.',
            'lineaInvestigacion.required' => 'La línea de investigación es obligatoria.',
            'propositoPrestamo.required' => 'El propósito del préstamo es obligatorio.',
            'duracionPropuestaMeses.required' => 'La duración propuesta es obligatoria.',
            'duracionPropuestaMeses.integer' => 'La duración debe ser un número entero.',
            'duracionPropuestaMeses.min' => 'La duración mínima es 1 mes.',
            'duracionPropuestaMeses.max' => 'La duración máxima permitida es 24 meses.',
            'justificacionExtendida.required' => 'Debes justificar por qué la investigación requiere más de 12 meses.',
            'justificacionExtendida.min' => 'La justificación debe tener al menos 20 caracteres.',
            'items.required' => 'Debes agregar al menos un espécimen.',
            'items.min' => 'Debes agregar al menos un espécimen.',
            'items.*.especimen_codigo_externo.required' => 'El código del espécimen es obligatorio.',
            'items.*.cantidad_solicitada.required' => 'La cantidad es obligatoria.',
            'items.*.cantidad_solicitada.min' => 'La cantidad mínima es 1.',
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
                justificacionExtendida: $this->duracionPropuestaMeses > 12 ? $this->justificacionExtendida : null,
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
                justificacionExtendida: $this->duracionPropuestaMeses > 12 ? $this->justificacionExtendida : null,
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
