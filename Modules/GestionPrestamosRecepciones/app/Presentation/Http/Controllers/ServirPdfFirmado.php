<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

/**
 * Controlador para servir el PDF o imagen del acta de préstamo firmada.
 */
final class ServirPdfFirmado
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

        if (! $acta->pdf_firmado_ruta || ! Storage::exists($acta->pdf_firmado_ruta)) {
            abort(404);
        }

        $isFirmaDigital = str_starts_with($acta->pdf_firmado_ruta, 'firmas-investigador/');
        $contentType = $isFirmaDigital ? 'image/png' : 'application/pdf';
        $filename = $isFirmaDigital ? 'acta-firma.png' : 'acta-firmada.pdf';

        return response(Storage::get($acta->pdf_firmado_ruta), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
