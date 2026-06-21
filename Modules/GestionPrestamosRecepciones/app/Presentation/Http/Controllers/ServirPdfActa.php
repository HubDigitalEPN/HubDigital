<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa\ConsultarDocumentoActaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa\ConsultarDocumentoActaInput;

/**
 * Controlador para servir el PDF del acta de préstamo.
 */
final class ServirPdfActa
{
    /**
     * @param string $id
     * @param ConsultarDocumentoActaHandler $handler
     * @return Response
     */
    public function __invoke(string $id, ConsultarDocumentoActaHandler $handler): Response
    {
        $user = auth()->user();

        $documento = $handler->handle(new ConsultarDocumentoActaInput(
            actaId: $id,
            usuarioId: (string) $user?->id,
            esCurador: $user?->rol === RolUsuario::CURADOR,
        ));

        if (! $documento->existe) {
            abort(404);
        }

        if (! $documento->autorizado) {
            abort(403);
        }

        if (! $documento->pdfRuta || ! Storage::exists($documento->pdfRuta)) {
            abort(404);
        }

        return response(Storage::get($documento->pdfRuta), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="acta-prestamo.pdf"',
        ]);
    }
}
