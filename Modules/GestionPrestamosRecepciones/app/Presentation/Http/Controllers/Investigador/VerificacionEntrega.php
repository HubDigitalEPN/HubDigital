<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ConsultarItemsPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarItemsPrestamo\ConsultarItemsPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarPrestamo\ConsultarPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarVerificacionEntrega\RegistrarVerificacionEntregaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarVerificacionEntrega\RegistrarVerificacionEntregaInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PrestamoNoEncontradoException;

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
     * @param ConsultarPrestamoHandler $handler
     * @param ConsultarItemsPrestamoHandler $itemsHandler
     * @return void
     */
    public function mount(
        string $id,
        ConsultarPrestamoHandler $handler,
        ConsultarItemsPrestamoHandler $itemsHandler,
    ): void {
        $this->id = $id;

        try {
            $prestamo = $handler->handle(new ConsultarPrestamoInput(
                prestamoId: $id,
                usuarioId: (string) auth()->id(),
            ));
        } catch (PrestamoNoEncontradoException) {
            abort(404);
        }

        if ($prestamo->investigadorId !== (string) auth()->id()) {
            abort(403);
        }

        if ($prestamo->estado->value !== 'en_transito') {
            abort(403);
        }

        // Pre-populate so itemPrestamoId is always available — wire:model on hidden inputs
        // does not push values into the component.
        $items = $itemsHandler->handle(new ConsultarItemsPrestamoInput(prestamoId: $id))->items;

        $this->observaciones = array_map(
            fn ($item) => [
                'itemPrestamoId' => $item->itemPrestamoId,
                'descripcion' => '',
            ],
            $items,
        );
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
     * @param ConsultarPrestamoHandler $handler
     * @param ConsultarItemsPrestamoHandler $itemsHandler
     * @return \Illuminate\View\View
     */
    public function render(
        ConsultarPrestamoHandler $handler,
        ConsultarItemsPrestamoHandler $itemsHandler,
    ): View {
        $prestamo = $handler->handle(new ConsultarPrestamoInput(
            prestamoId: $this->id,
            usuarioId: (string) auth()->id(),
        ));

        $items = $itemsHandler->handle(new ConsultarItemsPrestamoInput(prestamoId: $this->id))->items;

        return view('gestionprestamosrecepciones::investigador.verificacion-entrega', compact('prestamo', 'items'));
    }
}
