<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
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

        // Si el curador ya firmó criptográficamente, ese PDF (acta-documento con el
        // sello PAdES ya incrustado) es el documento oficial: se sirve tal cual, sin
        // regenerar. Su ruta la fija ValidarActaFirmada. Es vertical, sin la hoja de
        // especímenes.
        $firmadoCurador = 'actas-firmadas-curador/' . $acta->id . '.pdf';

        if (Storage::exists($firmadoCurador)) {
            return response(Storage::get($firmadoCurador), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Acta-' . $acta->numeroPrestamo . '.pdf"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        }

        // Acta vertical (todo menos especímenes). DomPDF no soporta orientación
        // mixta, así que la hoja de especímenes se genera aparte en horizontal y
        // se fusiona con FPDI (ver fusionar()).
        $actaPdf = Pdf::loadView('gestionprestamosrecepciones::pdf.acta-documento', [
            'acta' => $acta,
        ])->output();

        if (count($acta->items) === 0) {
            $contenido = $actaPdf;
        } else {
            $especimenesPdf = Pdf::loadView('gestionprestamosrecepciones::pdf.acta-documento', [
                'acta' => $acta,
                'soloEspecimenes' => true,
            ])->setPaper('a4', 'landscape')->output();

            $contenido = $this->fusionar($actaPdf, $especimenesPdf);
        }

        $filename = 'Acta-' . $acta->numeroPrestamo . '.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            // El PDF se genera al vuelo; evita que el navegador sirva una versión cacheada.
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Fusiona varios PDF (bytes) en uno solo respetando el tamaño y la orientación
     * de cada hoja original. Se usa para pegar la hoja de especímenes (horizontal)
     * al final del acta (vertical). Solo recibe PDF generados por nosotros (DomPDF).
     */
    private function fusionar(string ...$pdfs): string
    {
        $fpdi = new Fpdi();
        $fpdi->SetAutoPageBreak(false);

        foreach ($pdfs as $bytes) {
            $paginas = $fpdi->setSourceFile(StreamReader::createByString($bytes));

            for ($n = 1; $n <= $paginas; $n++) {
                $plantilla = $fpdi->importPage($n);
                $tamano = $fpdi->getTemplateSize($plantilla);
                $fpdi->AddPage($tamano['orientation'], [$tamano['width'], $tamano['height']]);
                $fpdi->useTemplate($plantilla);
            }
        }

        return $fpdi->Output('S');
    }
}
