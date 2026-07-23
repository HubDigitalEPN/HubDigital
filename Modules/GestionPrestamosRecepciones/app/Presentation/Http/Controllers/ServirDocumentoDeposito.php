<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Sirve, mediante streaming autenticado, un documento adjunto a una solicitud de
 * depósito o donación.
 *
 * Acceso permitido solo al curador o al depositante dueño de la solicitud. El
 * documento se identifica por su índice posicional dentro de `documentos_cargados`
 * (las claves son etiquetas legibles con espacios/acentos, no aptas para la URL).
 * Se entrega `inline` para previsualizarse en el navegador; con `?descargar=1` se
 * entrega como descarga.
 */
final class ServirDocumentoDeposito
{
    public function __invoke(string $id, int $indice): Response
    {
        $user = auth()->user();

        $deposito = SolicitudDepositoEloquentModel::find($id);
        abort_if($deposito === null, 404);

        $esCurador = $user?->esCurador() ?? false;
        $esDueno = (string) $deposito->investigador_id === (string) $user?->id;
        abort_unless($esCurador || $esDueno, 403);

        $documentos = $deposito->documentos_cargados ?? [];
        $rutas = array_values($documentos);
        $claves = array_keys($documentos);
        abort_unless(isset($rutas[$indice]), 404);

        $ruta = $rutas[$indice];
        abort_unless(Storage::disk('public')->exists($ruta), 404);

        $nombre = ($deposito->nombres_archivos_originales ?? [])[$claves[$indice]] ?? basename($ruta);
        $disposicion = request()->boolean('descargar') ? 'attachment' : 'inline';
        $mime = Storage::disk('public')->mimeType($ruta) ?: 'application/octet-stream';

        return response(Storage::disk('public')->get($ruta), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposicion.'; filename="'.addslashes($nombre).'"',
        ]);
    }
}
