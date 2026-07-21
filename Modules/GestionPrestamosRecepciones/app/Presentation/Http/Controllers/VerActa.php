<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoInput;

/**
 * Componente Livewire (Controlador) para visualizar el acta de préstamo.
 */
#[Layout('layouts.app', params: ['title' => 'Acta de préstamo'])]
final class VerActa extends Component
{
    public string $id;

    public function mount(string $id, ConsultarActaDocumentoHandler $handler): void
    {
        $this->id = $id;

        $acta = $handler->handle(new ConsultarActaDocumentoInput(actaId: $id));

        if ($acta === null) {
            abort(404);
        }

        $user = auth()->user();
        $esCurador = $user?->esCurador() ?? false;
        $esDueno = $acta->investigadorId === (string) $user?->id;

        if (! $esCurador && ! $esDueno) {
            abort(403);
        }
    }

    public function render(ConsultarActaDocumentoHandler $handler): View
    {
        $acta = $handler->handle(new ConsultarActaDocumentoInput(actaId: $this->id));

        if ($acta === null) {
            abort(404);
        }

        return view('gestionprestamosrecepciones::ver-acta', compact('acta'));
    }
}
