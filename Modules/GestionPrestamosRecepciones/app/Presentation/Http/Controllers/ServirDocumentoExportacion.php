<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

/**
 * Controlador para servir el documento de exportación de un acta de préstamo.
 */
final class ServirDocumentoExportacion
{
    /**
     * @param string $id
     * @return Response
     */
    public function __invoke(string $id): Response
    {
        $acta = ActaPrestamoModel::query()
            ->with('solicitud')
            ->findOrFail($id);

        $user = auth()->user();
        $isCurador = $user?->rol === RolUsuario::CURADOR;
        $isOwner = $acta->solicitud?->investigador_id === (string) $user?->id;

        if (! $isCurador && ! $isOwner) {
            abort(403);
        }

        if (! $acta->documento_exportacion_ruta || ! Storage::disk('public')->exists($acta->documento_exportacion_ruta)) {
            abort(404);
        }

        return response(Storage::disk('public')->get($acta->documento_exportacion_ruta), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="documento-exportacion.pdf"',
        ]);
    }
}
