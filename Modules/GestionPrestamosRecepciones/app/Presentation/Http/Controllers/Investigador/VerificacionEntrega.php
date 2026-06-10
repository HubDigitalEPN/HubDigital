<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarVerificacionEntrega\RegistrarVerificacionEntregaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarVerificacionEntrega\RegistrarVerificacionEntregaInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

/**
 * Componente Livewire para la verificación de entrega de especímenes.
 */
#[Layout('layouts.app', params: ['title' => 'Reportar recepción de especímenes'])]
final class VerificacionEntrega extends Component
{
    public string $id;

    public string $estadoEnvio = 'sin_novedades';

    /** @var array<int, array{itemPrestamoId: string, descripcion: string}> */
    public array $observaciones = [];

    /**
     * Inicializa el componente.
     *
     * @param string $id
     * @return void
     */
    public function mount(string $id): void
    {
        $this->id = $id;

        $prestamo = PrestamoEloquentModel::query()->with('acta.solicitud.items')->find($id);

        if ($prestamo === null) {
            abort(404);
        }

        if ($prestamo->acta?->solicitud?->investigador_id !== (string) auth()->id()) {
            abort(403);
        }

        if ($prestamo->estado !== 'en_transito') {
            abort(403);
        }

        // Pre-populate so itemPrestamoId is always available — wire:model on hidden inputs
        // does not push values into the component.
        $items = $prestamo->acta?->solicitud?->items ?? collect();
        $this->observaciones = $items->map(fn ($item) => [
            'itemPrestamoId' => (string) $item->id,
            'descripcion' => '',
        ])->values()->toArray();
    }

    /**
     * Registra la verificación de entrega.
     *
     * @param \Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarVerificacionEntrega\RegistrarVerificacionEntregaHandler $handler
     * @return void
     */
    public function registrar(RegistrarVerificacionEntregaHandler $handler): void
    {
        $this->validate([
            'estadoEnvio' => 'required|in:sin_novedades,con_novedades',
            'observaciones.*.descripcion' => 'nullable|string|max:500',
        ]);

        // Filter out items with no description — only send actual observations
        $conObservacion = array_values(array_filter(
            $this->observaciones,
            fn (array $obs) => trim($obs['descripcion'] ?? '') !== '',
        ));

        if ($this->estadoEnvio === 'con_novedades' && count($conObservacion) === 0) {
            $this->addError('observaciones', 'Debes ingresar al menos una observación cuando el estado es con novedades.');

            return;
        }

        $handler->handle(new RegistrarVerificacionEntregaInput(
            prestamoId: $this->id,
            estadoEnvio: $this->estadoEnvio,
            observaciones: $this->estadoEnvio === 'sin_novedades' ? [] : $conObservacion,
        ));

        $this->redirect(route('prestamos.investigador.prestamo.detalle', $this->id), navigate: true);
    }

    /**
     * Renderiza el componente.
     *
     * @return \Illuminate\View\View
     */
    public function render(): View
    {
        $prestamo = PrestamoEloquentModel::query()
            ->with('acta.solicitud.items')
            ->findOrFail($this->id);

        return view('gestionprestamosrecepciones::investigador.verificacion-entrega', compact('prestamo'));
    }
}
