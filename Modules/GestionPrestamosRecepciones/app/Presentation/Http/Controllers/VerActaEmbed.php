<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoInput;

/**
 * Sirve la previsualización embebida del acta (iframe).
 *
 * Renderiza exactamente la misma vista Blade que usa DescargarActaPdf para
 * generar el PDF (pdf.acta-documento), de modo que la previsualización y la
 * descarga sean idénticas en formato — sin plantillas divergentes.
 */
final class VerActaEmbed
{
    public function __invoke(string $id, ConsultarActaDocumentoHandler $handler): View|RedirectResponse
    {
        // Enlace "Descargar" (?download=1) delega en el generador de PDF real.
        if (request('download') == '1') {
            return redirect()->route('prestamos.acta.descargar-pdf', $id);
        }

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

        return view('gestionprestamosrecepciones::pdf.acta-documento', [
            'acta' => $acta,
            'embed' => true,
        ]);
    }
}
