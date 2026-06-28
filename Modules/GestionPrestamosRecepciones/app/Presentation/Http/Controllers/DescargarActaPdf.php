<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PatenteAnualNoConfigurada;

/**
 * Controlador para descargar el acta de préstamo como PDF generado por DomPDF.
 *
 * A diferencia de ServirPdfActa (que sirve un PDF ya almacenado en storage),
 * este controlador genera el PDF al vuelo a partir de la vista Blade, lo que
 * permite descargar el acta en cualquier estado (firmada o no).
 */
final class DescargarActaPdf
{
    /**
     * @param string $id
     * @param ConsultarActaDocumentoHandler $handler
     * @return Response
     */
    public function __invoke(string $id, ConsultarActaDocumentoHandler $handler): Response
    {
        $user = auth()->user();

        $acta = $handler->handle(new ConsultarActaDocumentoInput(actaId: $id));

        if ($acta === null) {
            abort(404);
        }

        if ($acta->patente === '') {
            $anio = (int) $acta->fechaInicio->format('Y');
            abort(422, PatenteAnualNoConfigurada::paraAnio($anio)->getMessage());
        }

        $esCurador = $user?->rol === RolUsuario::CURADOR;
        $esDueno = $acta->investigadorId === (string) $user?->id;

        if (! $esCurador && ! $esDueno) {
            abort(403);
        }

        $pdf = Pdf::loadView('gestionprestamosrecepciones::pdf.acta-documento', [
            'acta' => $acta,
        ]);

        $filename = 'Acta-' . $acta->numeroPrestamo . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
