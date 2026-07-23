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
 * Genera el PDF al vuelo a partir de la vista Blade (o sirve el firmado por el
 * curador si ya existe), lo que permite descargar el acta en cualquier estado
 * (firmada o no) sin depender de que el archivo esté almacenado.
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

        // ?sin_firma=1 fuerza el acta original SIN firmas (útil para contrastar con
        // la firmada), aun cuando ya existan firmas.
        $sinFirma = request('sin_firma') == '1';

        // Si el curador ya firmó, ese PDF (acta-documento con ambas firmas ya
        // incrustadas) es el documento oficial: se sirve tal cual, sin regenerar.
        // Su ruta la fija la validación del curador.
        $firmadoCurador = 'actas-firmadas-curador/'.$acta->id.'.pdf';

        if (! $sinFirma && Storage::exists($firmadoCurador)) {
            return response(Storage::get($firmadoCurador), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Acta-'.$acta->numeroPrestamo.'.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        }

        // Si el investigador firmó en canvas, su firma se guardó como PNG; se
        // re-incrusta para que el PDF refleje el estado real (firmado).
        $datos = ['acta' => $acta];

        if (! $sinFirma) {
            $firmaInvestigador = $pdf->leerImagenBase64('firmas-investigador/'.$acta->id.'.png');

            if ($firmaInvestigador !== null) {
                $datos['firmaBase64'] = $firmaInvestigador;
            }
        }

        $contenido = $pdf->generarActa($datos);

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
