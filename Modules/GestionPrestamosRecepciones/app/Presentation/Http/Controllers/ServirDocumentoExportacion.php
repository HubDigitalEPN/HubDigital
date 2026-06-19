<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa\ConsultarDocumentoActaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa\ConsultarDocumentoActaInput;

/**
 * Controlador para servir el documento de exportación de un acta de préstamo.
 */
final class ServirDocumentoExportacion
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

        if (! $documento->documentoExportacionRuta || ! Storage::disk('public')->exists($documento->documentoExportacionRuta)) {
            abort(404);
        }

        return response(Storage::disk('public')->get($documento->documentoExportacionRuta), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="documento-exportacion.pdf"',
        ]);
    }
}
