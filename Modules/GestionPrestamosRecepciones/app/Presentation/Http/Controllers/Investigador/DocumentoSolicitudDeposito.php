<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\ClienteFirmadorPreparado;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;

/** Sirve el formulario oficial generado o su copia final firmada desde almacenamiento privado. */
final class DocumentoSolicitudDeposito
{
    public function __invoke(
        Request $request,
        string $id,
        GeneradorPdfSolicitudDeposito $generador,
        AlmacenamientoDepositos $almacenamiento,
    ): Response {
        $inicio = hrtime(true);
        $solicitud = SolicitudDepositoEloquentModel::findOrFail($id);
        $usuario = $request->user();
        $esDueno = (string) $solicitud->investigador_id === (string) $usuario->id;
        abort_unless($esDueno || $usuario->esCurador() || $usuario->esReceptor(), 403);

        $servirOriginal = $request->boolean('original');
        $contenidoPendiente = !$servirOriginal ? app(ColaFirmasSolicitudR2::class)->contenidoPendiente($solicitud) : null;
        if ($contenidoPendiente !== null) {
            $contenido = $contenidoPendiente;
        } elseif (! $servirOriginal && $solicitud->solicitud_firmada_ruta !== null) {
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
            app()->terminating(static function () use ($contenido, $almacenamiento): void {
                try {
                    if (!Cache::add('oficio-preparado:'.hash('sha256', $contenido), true, now()->addMinutes(5))) return;
                    app(ClienteFirmadorPreparado::class)->prepararOriginal($contenido);
                    $almacenamiento->prepararConexion();
                } catch (\Throwable) {
                    // La firma mantiene su validación completa aunque no haya preparación previa.
                }
            });
        }

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($contenido),
            'Server-Timing' => 'pdf;dur='.number_format((hrtime(true)-$inicio)/1_000_000, 2, '.', ''),
            'Content-Disposition' => 'inline; filename="Solicitud-'.$solicitud->numero.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
