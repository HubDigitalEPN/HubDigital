<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;

/** El QR acredita el archivo validado sin publicar el oficio ni los datos personales. */
final class VerificarFirmaSolicitudDeposito
{
    public function __invoke(string $id, string $version, ColaFirmasSolicitudR2 $cola, AlmacenamientoDepositos $almacenamiento): Response
    {
        $solicitud = SolicitudDepositoEloquentModel::query()->whereKey($id)
            ->where('solicitud_documento_version', (int) $version)->whereNotNull('solicitud_firmada_en')->first();
        $estado = 'invalido';
        $mensaje = 'El documento no está firmado, fue eliminado o esta versión ya no está vigente.';
        $codigo = 404;
        if ($solicitud !== null) {
            $metadata = $solicitud->solicitud_firma_metadata ?? [];
            $aceptada = ($metadata['aceptable'] ?? false) === true
                && ($metadata['integridad_criptografica'] ?? false) === true
                && ($metadata['contenido_oficial_coincide'] ?? false) === true
                && is_string($solicitud->solicitud_firmada_sha256)
                && hash_equals($solicitud->solicitud_firmada_sha256, (string) ($metadata['pdf_sha256'] ?? ''));
            if ($aceptada) {
                try {
                    $registro = $cola->registro($solicitud);
                    if ($registro?->estado === 'pendiente' && $cola->disponible($solicitud)) {
                        $estado = 'pendiente'; $codigo = 202;
                        $mensaje = 'La firma fue validada. El guardado seguro continúa; vuelve a consultar en unos momentos.';
                    } elseif (($registro === null || $registro->estado === 'confirmado')
                        && hash_equals($solicitud->solicitud_firmada_sha256, $almacenamiento->sha256($solicitud->solicitud_firmada_ruta))) {
                        $estado = 'valido'; $codigo = 200;
                        $mensaje = 'El PDF conservado en R2 coincide íntegramente con el documento cuya firma electrónica fue validada.';
                    } else {
                        $codigo = 422;
                        $mensaje = 'No se pudo confirmar la integridad del PDF firmado.';
                    }
                } catch (\Throwable) {
                    $estado = 'no_disponible'; $codigo = 503;
                    $mensaje = 'La verificación no está disponible en este momento. Inténtalo nuevamente.';
                }
            } else {
                $codigo = 422;
                $mensaje = 'Este documento no cuenta con una validación criptográfica íntegra.';
            }
        }
        return response()->view('gestionprestamosrecepciones::publico.verificar-firma', [
            'estado' => $estado, 'mensaje' => $mensaje,
            'numero' => $solicitud?->numero, 'version' => $version,
            'fecha' => $solicitud?->solicitud_firmada_en?->timezone('America/Guayaquil')->format('d/m/Y H:i'),
        ], $codigo, ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
