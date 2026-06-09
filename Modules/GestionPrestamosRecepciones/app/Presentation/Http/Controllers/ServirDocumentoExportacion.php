<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use App\Enums\RolUsuario;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\ActaPrestamoModel;

final class ServirDocumentoExportacion
{
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

        $ruta = $acta->documento_exportacion_ruta;

        if (! $ruta) {
            abort(404);
        }

        // Disco por defecto (privado); 'public' solo como respaldo de archivos legados.
        $disk = Storage::exists($ruta) ? Storage::disk() : Storage::disk('public');

        if (! $disk->exists($ruta)) {
            abort(404);
        }

        return response($disk->get($ruta), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="documento-exportacion.pdf"',
        ]);
    }
}
