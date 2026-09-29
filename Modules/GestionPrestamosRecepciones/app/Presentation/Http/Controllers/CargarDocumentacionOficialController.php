<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial\CargarDocumentacionOficialHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial\CargarDocumentacionOficialInput;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\VerificarFirmaDocumentoJob;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DocumentoDepositoRechazado;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ValidacionPreviaDocumentoDeposito;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\CargarDocumentacionOficialRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\CargarDocumentacionOficialResource;

/** La API aplica el mismo control previo e independiente de documentos que el paso 3. */
final class CargarDocumentacionOficialController
{
    public function __construct(
        private readonly CargarDocumentacionOficialHandler $handler,
        private readonly AlmacenamientoDepositos $almacenamiento,
        private readonly ValidacionPreviaDocumentoDeposito $revision,
    ) {}

    public function __invoke(CargarDocumentacionOficialRequest $request, string $id): JsonResponse
    {
        $original = SolicitudDepositoEloquentModel::query()->whereKey($id)
            ->where('investigador_id', (string) $request->user()->id)->firstOrFail();
        abort_unless(in_array($original->estado, [
            EstadoSolicitudDeposito::EnBorrador->value, EstadoSolicitudDeposito::RequiereCorreccion->value,
        ], true), 409, 'La solicitud no admite cambios de documentos.');
        $rutas = [];
        $revisiones = [];
        $persistido = false;
        $anteriores = [];
        try {
            foreach ($request->file('documentos', []) as $nombre => $archivo) {
                abort_unless(in_array($nombre, $original->documentos_requeridos ?? [], true), 422,
                    'El documento no corresponde a una casilla requerida del expediente.');
                $revisiones[$nombre] = $this->revision->validar($archivo->getRealPath(), $nombre);
                $guardado = $this->almacenamiento->guardarArchivoConHuella($archivo, 'depositos/'.$id.'/documentos-api');
                $rutas[$nombre] = $guardado['ruta'];
                if (! hash_equals($revisiones[$nombre]['sha256'], $guardado['sha256'])) {
                    throw new DocumentoDepositoRechazado('pdf_inseguro', 'El PDF cambió después de revisarlo.');
                }
            }
            $output = DB::transaction(function () use ($request, $id, $original, $rutas, &$revisiones, &$anteriores) {
                $vigente = SolicitudDepositoEloquentModel::query()->whereKey($id)
                    ->where('investigador_id', (string) $request->user()->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($vigente->estado, [
                    EstadoSolicitudDeposito::EnBorrador->value, EstadoSolicitudDeposito::RequiereCorreccion->value,
                ], true), 409, 'La solicitud no admite cambios de documentos.');
                abort_unless($vigente->provincia_origen === $original->provincia_origen, 409,
                    'El origen cambió mientras se revisaban los documentos.');
                $documentos = $vigente->documentos_cargados ?? [];
                $nombres = $vigente->nombres_archivos_originales ?? [];
                $estados = $vigente->validacion_archivos ?? [];
                $firmas = $vigente->firmas_electronicas ?? [];
                $previas = $vigente->validacion_previa_documentos ?? [];
                foreach ($rutas as $nombre => $ruta) {
                    abort_unless(in_array($nombre, $vigente->documentos_requeridos ?? [], true), 409);
                    if (isset($documentos[$nombre])) $anteriores[] = $documentos[$nombre];
                    $documentos[$nombre] = $ruta;
                    $nombres[$nombre] = $request->file('documentos')[$nombre]->getClientOriginalName();
                    $estados[$nombre] = 'valido';
                    $firmas[$nombre] = 'validando';
                    $token = (string) Str::uuid();
                    $revisiones[$nombre] += ['ruta' => $ruta, 'verificacion_id' => $token,
                        'firma_verificada' => false, 'comprobado_en' => now()->toIso8601String()];
                    $previas[$nombre] = $revisiones[$nombre];
                }
                if ($vigente->solicitud_firmada_ruta !== null) {
                    $anteriores[] = $vigente->solicitud_firmada_ruta;
                }
                $metadata = $vigente->extraccion_metadatos ?? [];
                unset($metadata['ejecucion_id'], $metadata['confirmacion_humana'], $metadata['validacion_contenido']);
                $vigente->forceFill([
                    'documentos_cargados' => $documentos, 'nombres_archivos_originales' => $nombres,
                    'validacion_archivos' => $estados, 'firmas_electronicas' => $firmas,
                    'validacion_previa_documentos' => $previas, 'extraccion_estado' => 'pendiente',
                    'extraccion_metadatos' => $metadata, 'documentos_procesados' => [],
                    'solicitud_documento_version' => (int) $vigente->solicitud_documento_version + 1,
                    'solicitud_firmada_ruta' => null, 'solicitud_firmada_sha256' => null,
                    'solicitud_firmada_en' => null, 'solicitud_firma_metadata' => [],
                ])->save();

                return ($this->handler)(new CargarDocumentacionOficialInput(
                    solicitudId: $id, documentos: $documentos, documentosAlmacenados: $rutas,
                ));
            });
            $persistido = true;
            foreach ($rutas as $nombre => $ruta) {
                $token = $revisiones[$nombre]['verificacion_id'];
                try {
                    VerificarFirmaDocumentoJob::dispatch($id, $nombre, $ruta, $token);
                } catch (\Throwable $error) {
                    report($error);
                    (new VerificarFirmaDocumentoJob($id, $nombre, $ruta, $token))->failed($error);
                }
            }
            foreach ($anteriores as $ruta) {
                try { $this->almacenamiento->eliminar($ruta); }
                catch (\Throwable $error) { report($error); }
            }

            return CargarDocumentacionOficialResource::make($output)->response();
        } catch (\InvalidArgumentException $error) {
            return response()->json(['message' => $error->getMessage(),
                'estado' => $error instanceof DocumentoDepositoRechazado ? $error->estado : 'pdf_inseguro'], 422);
        } finally {
            if (! $persistido) {
                foreach ($rutas as $ruta) {
                    try { $this->almacenamiento->eliminar($ruta); }
                    catch (\Throwable $error) { report($error); }
                }
            }
        }
    }
}
