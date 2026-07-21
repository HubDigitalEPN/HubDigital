<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa\ConsultarDocumentoActaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa\ConsultarDocumentoActaInput;

/**
 * Controlador para servir el PDF o imagen del acta de préstamo firmada.
 */
final class ServirPdfFirmado
{
    public function __invoke(string $id, ConsultarDocumentoActaHandler $handler): Response
    {
        $user = auth()->user();

        $documento = $handler->handle(new ConsultarDocumentoActaInput(
            actaId: $id,
            usuarioId: (string) $user?->id,
            esCurador: $user?->esCurador() ?? false,
        ));

        if (! $documento->existe) {
            abort(404);
        }

        if (! $documento->autorizado) {
            abort(403);
        }

        if (! $documento->pdfFirmadoRuta || ! Storage::exists($documento->pdfFirmadoRuta)) {
            abort(404);
        }

        $isFirmaDigital = str_starts_with($documento->pdfFirmadoRuta, 'firmas-investigador/');
        $contentType = $isFirmaDigital ? 'image/png' : 'application/pdf';
        $filename = $isFirmaDigital ? 'acta-firma.png' : 'acta-firmada.pdf';

        return response(Storage::get($documento->pdfFirmadoRuta), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
