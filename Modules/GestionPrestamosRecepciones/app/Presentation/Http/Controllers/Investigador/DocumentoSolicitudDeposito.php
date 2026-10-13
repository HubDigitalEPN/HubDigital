<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\ClienteFirmadorPreparado;

/** Sirve el formulario oficial generado o su copia final firmada desde almacenamiento privado. */
final class DocumentoSolicitudDeposito
{
    public function __invoke(
        Request $request,
        string $id,
        GeneradorPdfSolicitudDeposito $generador,
        AlmacenamientoDepositos $almacenamiento,
    ): Response {
        $solicitud = SolicitudDepositoEloquentModel::findOrFail($id);
        $usuario = $request->user();
        $esDueno = (string) $solicitud->investigador_id === (string) $usuario->id;
        abort_unless($esDueno || $usuario->esCurador() || $usuario->esReceptor(), 403);

        $servirOriginal = $request->boolean('original');
        if (! $servirOriginal && $solicitud->solicitud_firmada_ruta !== null) {
            abort_unless(
                $almacenamiento->existe($solicitud->solicitud_firmada_ruta),
                409,
                'La copia firmada no está disponible. No se servirá el documento original como si estuviera firmado.',
            );
            abort_unless(
                is_string($solicitud->solicitud_firmada_sha256)
                    && $solicitud->solicitud_firmada_sha256 !== ''
                    && hash_equals(
                        $solicitud->solicitud_firmada_sha256,
                        $almacenamiento->sha256($solicitud->solicitud_firmada_ruta),
                    ),
                409,
                'La copia firmada no supera la verificación de integridad.',
            );
            $contenido = $almacenamiento->obtener($solicitud->solicitud_firmada_ruta);
        } else {
            abort_unless($esDueno || $usuario->esCurador(), 403);
            $contenido = $generador->generar($solicitud);
            app(ClienteFirmadorPreparado::class)->prepararOriginal($contenido);
        }

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Solicitud-'.$solicitud->numero.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
