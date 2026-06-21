<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoInput;

/**
 * Componente Livewire (Controlador) para visualizar el acta de préstamo de forma embebida.
 */
#[Layout('layouts.print')]
final class VerActaEmbed extends Component
{
    public string $id;

    public bool $isEmbed = true;

    public bool $sinFirma = false;

    /**
     * @param string $id
     * @param ConsultarActaDocumentoHandler $handler
     * @return void
     */
    public function mount(string $id, ConsultarActaDocumentoHandler $handler): void
    {
        $this->id = $id;
        $this->sinFirma = request()->boolean('sin_firma');

        $acta = $handler->handle(new ConsultarActaDocumentoInput(actaId: $id));

        if ($acta === null) {
            abort(404);
        }

        $user = auth()->user();
        $esCurador = $user?->rol === RolUsuario::CURADOR;
        $esDueno = $acta->investigadorId === (string) $user?->id;

        if (! $esCurador && ! $esDueno) {
            abort(403);
        }
    }

    /**
     * @param ConsultarActaDocumentoHandler $handler
     * @return View
     */
    public function render(ConsultarActaDocumentoHandler $handler): View
    {
        $acta = $handler->handle(new ConsultarActaDocumentoInput(actaId: $this->id));

        if ($acta === null) {
            abort(404);
        }

        return view('gestionprestamosrecepciones::ver-acta', compact('acta'));
    }
}
