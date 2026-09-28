<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\FirmaPdfJava;
use Modules\GestionPrestamosRecepciones\Presentation\Support\PerfilFirmaPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito;

/** Java crea y verifica la firma; PHP coordina el original y la persistencia. */
final class FirmarSolicitudDeposito
{
    public function __invoke(
        Request $request,
        string $id,
        GeneradorPdfSolicitudDeposito $generador,
        ValidacionFirmaElectronicaPort $validador,
        AlmacenamientoDepositos $almacenamiento,
    ): JsonResponse {
        $request->attributes->set('credencial_firma_sensible', $request->hasFile('certificado'));
        $request->validate(FirmaPdfJava::reglas());

        $solicitud = SolicitudDepositoEloquentModel::findOrFail($id);
        abort_unless((string) $solicitud->investigador_id === (string) $request->user()->id, 403);
        abort_unless(in_array($solicitud->estado, [
            EstadoSolicitudDeposito::EnBorrador->value,
            EstadoSolicitudDeposito::RequiereCorreccion->value,
        ], true), 409, 'La solicitud ya fue enviada y no admite una nueva firma.');

        $pdfOriginal = $generador->generar($solicitud);
        $originalTemporal = DirectorioTemporalHubDigital::crearArchivo('firma-solicitud', 'original-', 16 * 1024 * 1024);
        if (file_put_contents($originalTemporal, $pdfOriginal, LOCK_EX) === false) {
            DirectorioTemporalHubDigital::eliminar(dirname($originalTemporal));
            abort(500, 'No se pudo preparar el documento oficial.');
        }

        $versionEsperada = (int) $solicitud->solicitud_documento_version;
        $ruta = 'solicitudes-deposito/firmadas/'.$solicitud->id
            .'-v'.((int) $solicitud->solicitud_documento_version)
            .'-'.Str::uuid().'.pdf';
        $persistido = false;
        $guardado = false;
        $archivoJava = null;
        try {
            $archivoJava = app(FirmaPdfJava::class)->preparar($request, $originalTemporal, PerfilFirmaPdf::SOLICITUD_DEPOSITANTE);
            $rutaAbsoluta = $archivoJava->ruta();
            $validacion = $validador->verificarFirmaDetallada($rutaAbsoluta, $originalTemporal);
            if (! $validacion->esAceptable()) {
                return response()->json([
                    'message' => $validacion->error ?: 'La firma no superó la validación criptográfica e integral.',
                    'validacion' => $validacion->toArray(),
                ], 422);
            }

            $firmaMetadata = $validacion->toArray();
            $firmaMetadata['firmante_usuario_id'] = (string) $request->user()->id;
            $firmaMetadata['proposito'] = 'solicitud_deposito';
            $firmaMetadata['pdf_sha256'] = hash_file('sha256', $rutaAbsoluta);
            $archivoFirmado = new UploadedFile($rutaAbsoluta, 'solicitud-firmada.pdf', 'application/pdf', null, true);
            $rutaGuardada = $almacenamiento->guardarSubidoComo($archivoFirmado, $ruta);
            abort_unless($rutaGuardada === $ruta, 500, 'No se pudo guardar la solicitud firmada.');
            $guardado = true;

            $rutaAnterior = DB::transaction(function () use (
                $id,
                $request,
                $versionEsperada,
                $ruta,
                $firmaMetadata,
            ): ?string {
                $vigente = SolicitudDepositoEloquentModel::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                abort_unless((string) $vigente->investigador_id === (string) $request->user()->id, 403);
                abort_unless(in_array($vigente->estado, [
                    EstadoSolicitudDeposito::EnBorrador->value,
                    EstadoSolicitudDeposito::RequiereCorreccion->value,
                ], true), 409, 'La solicitud cambió de estado mientras se firmaba.');
                abort_unless(
                    (int) $vigente->solicitud_documento_version === $versionEsperada,
                    409,
                    'El documento cambió mientras se firmaba. Genera y firma la versión vigente.',
                );

                $anterior = is_string($vigente->solicitud_firmada_ruta)
                    ? $vigente->solicitud_firmada_ruta
                    : null;
                $vigente->forceFill([
                    'solicitud_firmada_ruta' => $ruta,
                    'solicitud_firmada_sha256' => $firmaMetadata['pdf_sha256'],
                    'solicitud_firmada_en' => now(),
                    'solicitud_firma_metadata' => $firmaMetadata,
                ])->save();

                return $anterior;
            });
            $persistido = true;

            Log::info('Firma de solicitud persistida', [
                'solicitud_id' => $id,
                'version' => $versionEsperada,
                'sha256' => $firmaMetadata['pdf_sha256'],
                'objeto_verificado' => $almacenamiento->existe($ruta),
            ]);

            if (is_string($rutaAnterior) && $rutaAnterior !== '' && $rutaAnterior !== $ruta) {
                try {
                    $almacenamiento->eliminar($rutaAnterior);
                } catch (\Throwable $e) {
                    Log::warning('No se pudo eliminar una version anterior de la solicitud firmada', [
                        'solicitud_id' => (string) $solicitud->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\InvalidArgumentException $e) {
            if ($guardado && ! $persistido) $almacenamiento->eliminar($ruta);
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            if ($guardado && ! $persistido) {
                $almacenamiento->eliminar($ruta);
            }
            throw $e;
        } finally {
            $archivoJava?->limpiar();
            $request->request->remove('clave_certificado');
            DirectorioTemporalHubDigital::eliminar(dirname($originalTemporal));
        }

        return response()->json([
            'message' => 'Solicitud firmada y validada por el Firmador HubDigital.',
        ]);
    }
}
