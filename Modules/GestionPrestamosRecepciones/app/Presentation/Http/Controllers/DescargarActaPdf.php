<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;
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
    public function __invoke(string $id, ConsultarActaDocumentoHandler $handler, PdfGeneratorPort $pdf): Response
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

        // Si el curador ya firmó criptográficamente, ese PDF (acta-documento con el
        // sello PAdES ya incrustado) es el documento oficial: se sirve tal cual, sin
        // regenerar. Su ruta la fija ValidarActaFirmada.
        $firmadoCurador = 'actas-firmadas-curador/'.$acta->id.'.pdf';

        if (Storage::exists($firmadoCurador)) {
            return response(Storage::get($firmadoCurador), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Acta-'.$acta->numeroPrestamo.'.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        }

        $contenido = $pdf->generarActa(['acta' => $acta]);

        $filename = 'Acta-'.$acta->numeroPrestamo.'.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            // El PDF se genera al vuelo; evita que el navegador sirva una versión cacheada.
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
