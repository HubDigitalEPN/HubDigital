<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Exceptions\ModeloIANoDisponibleException;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/**
 * Trabajo en segundo plano para la extracción y validación de datos de documentos.
 *
 * Procesa de forma asíncrona una lista de documentos PDF cargados por el investigador,
 * extrayendo información mediante IA, verificando firmas electrónicas y finalmente
 * integrando estos datos en la solicitud de depósito correspondiente.
 */
final class ExtraccionDatosDocumentoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 300;

    /**
     * @param  string  $solicitudId  ID de la solicitud de depósito.
     * @param  array<string, string>  $documentos  Mapeo de [nombre_documento => ruta_storage].
     */
    public function __construct(
        private readonly string $solicitudId,
        private readonly array $documentos,
        private readonly string $versionDocumental = '',
        private readonly string $ejecucionId = '',
    ) {
        if (! config('hubdigital.validation_mode')) {
            return;
        }

        $cola = trim((string) config('hubdigital.validation_queue', ''));
        if ($cola === 'default' || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', $cola) !== 1) {
            throw new \RuntimeException('La validacion requiere una cola dedicada valida distinta de default.');
        }

        $this->onQueue($cola);
    }

    /**
     * Ejecuta el procesamiento de los documentos.
     */
    public function handle(
        ExtraccionDatosDocumentoPort $extraccion,
        ValidacionFirmaElectronicaPort $validadorFirma,
        SolicitudDepositoRepositoryInterface $repo,
        TransactionManagerPort $transactionManager,
        EventPublisherPort $eventPublisher,
        AnalizadorDocumentoAmbiental $analizadorDocumento,
        AlmacenamientoDepositos $almacenamiento,
    ): void {
        if (! $this->esVersionVigente()) {
            Log::info('ExtraccionDatosDocumentoJob: se descartó una versión documental obsoleta', [
                'solicitudId' => $this->solicitudId,
            ]);

            return;
        }

        $this->actualizarEstadoSiVersionVigente('procesando', [], true);

        $documentoEnProceso = null;

        try {
            $acumulado = [
                'nroPermisoRecoleccion' => null,
                'nroPermisoMovilizacion' => null,
                'grupoAnimal' => null,
                'provinciaOrigen' => null,
                'localidad' => null,
                'origenDonacion' => null,
                'nombreInvestigador' => null,
                'nroIndividuos' => null,
                'nroMorfoespecies' => null,
                'nroLotes' => null,
            ];

            $procesados = [];
            $metadatosExtraccion = [
                'motor' => 'local',
                'ejecucion_id' => $this->ejecucionId,
                'requiere_revision_humana' => true,
                'confirmacion_humana' => ['estado' => 'pendiente'],
                'documentos' => [],
                'campos' => [],
            ];
            $documentosAnalizados = [];
            $modelSolicitud = SolicitudDepositoEloquentModel::find($this->solicitudId);
            $nombresOriginales = $modelSolicitud?->nombres_archivos_originales ?? [];

            foreach ($this->documentos as $nombre => $ruta) {
                $documentoEnProceso = $nombre;
                $previa = $modelSolicitud?->validacion_previa_documentos[$nombre] ?? [];
                if (($previa['ruta'] ?? null) !== $ruta
                    || ($previa['firma_verificada'] ?? false) !== true
                    || ($modelSolicitud?->validacion_archivos[$nombre] ?? null) !== 'valido'
                    || ! in_array($modelSolicitud?->firmas_electronicas[$nombre] ?? '', ['firmado', 'firmado_sin_revocacion'], true)) {
                    throw new \RuntimeException('Cada documento debe completar su control previo y firma en el paso 3.');
                }
                if (! hash_equals((string) ($previa['sha256'] ?? ''), $almacenamiento->sha256($ruta))) {
                    throw new \RuntimeException('El PDF cambió después de su comprobación de firma.');
                }
                $parcial = $extraccion->extraerDatos([$nombre => $ruta]);

                foreach ([
                    'nroPermisoRecoleccion' => $parcial->nroPermisoRecoleccion,
                    'nroPermisoMovilizacion' => $parcial->nroPermisoMovilizacion,
                    'grupoAnimal' => $parcial->grupoAnimal,
                    'provinciaOrigen' => null,
                    'localidad' => $parcial->localidad,
                    'origenDonacion' => $parcial->origenDonacion,
                    'nombreInvestigador' => $parcial->nombreInvestigador,
                    'nroIndividuos' => $parcial->nroIndividuos,
                    'nroMorfoespecies' => $parcial->nroMorfoespecies,
                    'nroLotes' => $parcial->nroLotes,
                ] as $campo => $valor) {
                    if ($acumulado[$campo] === null && $valor !== null) {
                        $acumulado[$campo] = $valor;
                    }
                }

                $detalleDocumento = $parcial->metadatosExtraccion['documentos'][$nombre] ?? [];
                $metadatosExtraccion['documentos'][$nombre] = $detalleDocumento;
                $tipoEsperado = $analizadorDocumento->tipoEsperadoParaNombre($nombre);
                $analisis = is_array($detalleDocumento['analisis'] ?? null)
                    ? $detalleDocumento['analisis']
                    : [];
                if ($tipoEsperado !== null) {
                    $documentosAnalizados[$tipoEsperado] = $analisis;
                    $this->persistirDocumentoRegulatorio(
                        nombre: $nombre,
                        nombreOriginal: $nombresOriginales[$nombre] ?? null,
                        ruta: $ruta,
                        tipoEsperado: $tipoEsperado,
                        detalleDocumento: $detalleDocumento,
                        analisis: $analisis,
                        almacenamiento: $almacenamiento,
                    );
                }
                foreach (($parcial->metadatosExtraccion['campos'] ?? []) as $campo => $detalle) {
                    if (! isset($metadatosExtraccion['campos'][$campo])) {
                        $metadatosExtraccion['campos'][$campo] = $detalle;
                    }
                }

                $procesados[] = $nombre;
                $affected = SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                    ->update(['documentos_procesados' => json_encode($procesados)]);
                Log::debug('ExtraccionDatosDocumentoJob: progreso', [
                    'solicitudId' => $this->solicitudId,
                    'procesados' => $procesados,
                    'affected_rows' => $affected,
                    'db_value' => SolicitudDepositoEloquentModel::find($this->solicitudId)?->documentos_procesados,
                ]);
            }

            $tiposRequeridos = array_values(array_filter(array_map(
                fn (string $nombre): ?string => $analizadorDocumento->tipoEsperadoParaNombre($nombre),
                $modelSolicitud?->documentos_requeridos ?? [],
            )));
            $validacionContenido = $analizadorDocumento->validarExpediente($documentosAnalizados, $tiposRequeridos);
            $metadatosExtraccion['validacion_contenido'] = $validacionContenido;
            if (($validacionContenido['autocompletado_habilitado'] ?? false) !== true) {
                foreach ($metadatosExtraccion['campos'] as $campo => &$detalleCampo) {
                    if (($detalleCampo['metodo'] ?? null) !== 'clasificador_ambiental') {
                        continue;
                    }
                    $acumulado[$campo] = null;
                    $detalleCampo['bloqueado_por_expediente'] = true;
                    $detalleCampo['motivo_bloqueo'] = 'La validación cruzada del expediente detectó faltantes, ambigüedad o contradicciones.';
                }
                unset($detalleCampo);
            }

            // Los códigos de muestra son el vínculo entre la guía y cada fila de la
            // matriz. Se conservan sin fabricar una identificación taxonómica.
            $registrosSugeridos = [];
            foreach (($validacionContenido['autocompletado_habilitado'] ?? false) === true ? $metadatosExtraccion['documentos'] : [] as $detalle) {
                $analisis = $detalle['analisis'] ?? [];
                foreach (($analisis['codigos_muestra'] ?? []) as $codigo) {
                    $registrosSugeridos[$codigo] = [
                        'recordNumber' => $codigo,
                        'researchPermit' => $analisis['numero_autorizacion'] ?? null,
                        'transportPermit' => ($analisis['tipo_detectado'] ?? null) === AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION
                            ? ($analisis['numero_documento'] ?? null)
                            : null,
                        'verbatimLocality' => $analisis['origen'] ?? null,
                        'gruposBiologicos' => $analisis['grupos_biologicos'] ?? [],
                    ];
                }
            }
            $metadatosExtraccion['registros_sugeridos'] = array_values($registrosSugeridos);

            // Consumir únicamente dictámenes Java asociados a la revisión de cada PDF.
            $firmas = [];
            $tokensFirmas = [];
            $firmasPersistidas = SolicitudDepositoEloquentModel::query()->find($this->solicitudId);
            foreach ($this->documentos as $nombre => $ruta) {
                $previa = $firmasPersistidas?->validacion_previa_documentos[$nombre] ?? [];
                $estadoFirma = $firmasPersistidas?->firmas_electronicas[$nombre] ?? '';
                if (($previa['ruta'] ?? null) !== $ruta
                    || ($previa['firma_verificada'] ?? false) !== true
                    || ! in_array($estadoFirma, ['firmado', 'firmado_sin_revocacion'], true)) {
                    throw new \RuntimeException('La comprobación de firma cambió durante el análisis. Vuelve al paso 3.');
                }
                $tokensFirmas[$nombre] = $previa['verificacion_id'] ?? '';

                $firmas[$nombre] = $estadoFirma;
                $metadatosExtraccion['documentos'][$nombre]['firma_electronica'] = $estadoFirma;

                $tipoEsperado = $analizadorDocumento->tipoEsperadoParaNombre($nombre);
                if ($tipoEsperado !== null) {
                    DB::table('recepciones.documentos_regulatorios')
                        ->where('solicitud_id', $this->solicitudId)
                        ->where('tipo_esperado', $tipoEsperado)
                        ->where('ruta', $ruta)
                        ->update([
                            'firma_estado' => $estadoFirma,
                            'firma_verificada_en' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }

            Log::debug('ExtraccionDatosDocumentoJob: firmas electrónicas', [
                'solicitudId' => $this->solicitudId,
                'firmas' => $firmas,
            ]);

            $datosIntegrados = new DatosIntegradosDocumento(
                nroPermisoRecoleccion: $acumulado['nroPermisoRecoleccion'],
                nroPermisoMovilizacion: $acumulado['nroPermisoMovilizacion'],
                grupoAnimal: $acumulado['grupoAnimal'],
                provinciaOrigen: $acumulado['provinciaOrigen'],
                localidad: $acumulado['localidad'],
                origenDonacion: $acumulado['origenDonacion'],
                nombreInvestigador: $acumulado['nombreInvestigador'],
                nroIndividuos: $acumulado['nroIndividuos'],
                nroMorfoespecies: $acumulado['nroMorfoespecies'],
                nroLotes: $acumulado['nroLotes'],
                metadatosExtraccion: $metadatosExtraccion,
            );

            // OCR puede tardar varios minutos. No integramos un resultado que ya
            // dejó de corresponder a los documentos vigentes del expediente.
            if (! $this->esVersionVigente()) {
                Log::info('ExtraccionDatosDocumentoJob: resultados descartados por cambio documental', [
                    'solicitudId' => $this->solicitudId,
                ]);

                return;
            }

            $eventos = [];
            $aplicada = false;

            // Persistir firmas después de la integración de dominio para evitar
            // inconsistencia si la transacción anterior falla.
            $transactionManager->executeTransactional(function () use ($repo, $datosIntegrados, $firmas, $tokensFirmas, &$metadatosExtraccion, &$eventos, &$aplicada): void {
                $modelo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)->lockForUpdate()->firstOrFail();
                if (! $this->coincideVersionYEjecucion($modelo)) {
                    return;
                }
                foreach ($tokensFirmas as $nombre => $token) {
                    $previa = $modelo->validacion_previa_documentos[$nombre] ?? [];
                    if (($previa['verificacion_id'] ?? '') !== $token
                        || ($previa['firma_verificada'] ?? false) !== true) return;
                }
                $solicitud = $repo->buscarPorIdParaActualizar(SolicitudDepositoId::from($this->solicitudId));
                if ($solicitud === null) {
                    throw SolicitudNoEncontradaException::conId($this->solicitudId);
                }
                $solicitud->integrarDatosDeDocumentos($datosIntegrados, array_keys($this->documentos));
                $repo->guardar($solicitud);
                $metadatosVigentes = $modelo->extraccion_metadatos ?? [];
                $revisionHumana = $metadatosVigentes['revision_documental'] ?? null;
                $historialRevision = $metadatosVigentes['revision_documental_historial'] ?? [];

                // Una extracción tardía puede enriquecer sus resultados, pero nunca
                // reemplazar una resolución humana de la misma versión documental.
                if (is_array($revisionHumana)) {
                    $metadatosExtraccion['revision_documental'] = $revisionHumana;
                    $metadatosExtraccion['revision_documental_historial'] = $historialRevision;
                }

                $modelo->forceFill([
                    'firmas_electronicas' => array_replace($modelo->firmas_electronicas ?? [], $firmas),
                    'extraccion_metadatos' => $metadatosExtraccion,
                    'extraccion_estado' => 'completada',
                ])->save();
                $eventos = $solicitud->pullEvents();
                $aplicada = true;
            });
            if ($aplicada) {
                foreach ($eventos as $event) {
                    $eventPublisher->publish($event);
                }
            }
        } catch (ModeloIANoDisponibleException $e) {
            Log::warning('ExtraccionDatosDocumentoJob: modelo de IA no disponible', [
                'solicitudId' => $this->solicitudId,
                'error' => $e->getMessage(),
            ]);

            $this->registrarFalloTecnicoSiVersionVigente($e, $documentoEnProceso, 'motor_no_disponible');
            $this->actualizarEstadoSiVersionVigente('error_modelo');
        } catch (\Throwable $e) {
            Log::error('ExtraccionDatosDocumentoJob: error al procesar documentos', [
                'solicitudId' => $this->solicitudId,
                'error' => $e->getMessage(),
            ]);

            $this->registrarFalloTecnicoSiVersionVigente($e, $documentoEnProceso);

            // La infraestructura de colas conserva el intento y aplica backoff. El
            // estado final se escribe únicamente desde failed(), una vez agotados.
            throw $e;
        }
    }

    public function failed(\Throwable $error): void
    {
        if (! $this->esVersionVigente()) {
            return;
        }

        Log::error('ExtraccionDatosDocumentoJob: falló tras los reintentos configurados', [
            'solicitudId' => $this->solicitudId,
            'error' => $error->getMessage(),
        ]);

        $this->actualizarEstadoSiVersionVigente('fallida');
    }

    private function esVersionVigente(): bool
    {
        $modelo = SolicitudDepositoEloquentModel::query()
            ->whereKey($this->solicitudId)
            ->first();

        return $modelo !== null && $this->coincideVersionYEjecucion($modelo);
    }

    /** @param list<string>|null $procesados */
    private function actualizarEstadoSiVersionVigente(
        string $estado,
        ?array $procesados = null,
        bool $limpiarFalloAnterior = false,
    ): void
    {
        DB::transaction(function () use ($estado, $procesados, $limpiarFalloAnterior): void {
            $modelo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)->lockForUpdate()->first();
            if ($modelo === null || ! $this->coincideVersionYEjecucion($modelo)) {
                return;
            }
            $cambios = ['extraccion_estado' => $estado];
            if ($procesados !== null) {
                $cambios['documentos_procesados'] = $procesados;
            }
            if ($limpiarFalloAnterior) {
                $metadatos = $modelo->extraccion_metadatos ?? [];
                unset($metadatos['fallo_extraccion']);
                $cambios['extraccion_metadatos'] = $metadatos;
            }
            $modelo->forceFill($cambios)->save();
        });
    }

    private function registrarFalloTecnicoSiVersionVigente(
        \Throwable $error,
        ?string $documento,
        ?string $codigo = null,
    ): void {
        DB::transaction(function () use ($error, $documento, $codigo): void {
            $modelo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)->lockForUpdate()->first();
            if ($modelo === null || ! $this->coincideVersionYEjecucion($modelo)) {
                return;
            }

            $codigo ??= $this->clasificarFalloTecnico($error);
            $metadatos = $modelo->extraccion_metadatos ?? [];
            $metadatos['fallo_extraccion'] = [
                'codigo' => $codigo,
                'documento' => $documento,
                'mensaje_usuario' => $this->mensajePublicoParaFallo($codigo, $documento),
                'reintento_permitido' => true,
                'ocurrido_en' => now()->toIso8601String(),
                'version_documental' => $this->versionDocumental,
            ];
            $modelo->forceFill(['extraccion_metadatos' => $metadatos])->save();
        });
    }

    private function clasificarFalloTecnico(\Throwable $error): string
    {
        $mensaje = mb_strtolower($error->getMessage());

        if (str_contains($mensaje, 'r2') || str_contains($mensaje, 'almacenamiento')) {
            return 'almacenamiento_no_disponible';
        }
        if (str_contains($mensaje, 'pdf') || str_contains($mensaje, 'poppler') || str_contains($mensaje, 'tesseract')) {
            return 'lectura_pdf_fallida';
        }

        return 'procesamiento_interno_interrumpido';
    }

    private function mensajePublicoParaFallo(string $codigo, ?string $documento): string
    {
        $etiqueta = is_string($documento) && trim($documento) !== ''
            ? ' «'.trim($documento).'»'
            : '';

        return match ($codigo) {
            'motor_no_disponible' => 'El motor local de lectura no está disponible temporalmente. Conservamos tus archivos; vuelve a intentar el análisis.',
            'almacenamiento_no_disponible' => 'No pudimos recuperar temporalmente el documento'.$etiqueta.'. Conservamos tu expediente; vuelve a intentar el análisis.',
            'lectura_pdf_fallida' => 'No pudimos leer técnicamente el documento'.$etiqueta.'. Comprueba que el PDF abra correctamente y vuelve a intentar.',
            default => 'El análisis se interrumpió mientras procesábamos el documento'.$etiqueta.'. Conservamos tus archivos; vuelve a intentar.',
        };
    }

    private function coincideVersionYEjecucion(SolicitudDepositoEloquentModel $modelo): bool
    {
        if (self::huellaDocumental($modelo->documentos_cargados ?? []) !== $this->versionDocumental) {
            return false;
        }

        if ($this->ejecucionId === '') {
            return true;
        }

        $metadatos = $modelo->extraccion_metadatos ?? [];

        return hash_equals((string) ($metadatos['ejecucion_id'] ?? ''), $this->ejecucionId);
    }

    /** @param array<string, string> $documentos */
    public static function huellaDocumental(array $documentos): string
    {
        ksort($documentos);

        return hash('sha256', json_encode($documentos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $detalleDocumento @param array<string, mixed> $analisis */
    private function persistirDocumentoRegulatorio(
        string $nombre,
        ?string $nombreOriginal,
        string $ruta,
        string $tipoEsperado,
        array $detalleDocumento,
        array $analisis,
        AlmacenamientoDepositos $almacenamiento,
    ): void {
        if (! $this->esVersionVigente()) {
            return;
        }
        $ahora = now();
        $contenido = array_intersect_key($analisis, array_flip([
            'numero_documento', 'numero_autorizacion', 'titular', 'organizacion', 'ruc',
            'proyecto', 'emitido_en', 'valido_desde', 'valido_hasta', 'origen', 'destino',
            'codigos_muestra', 'grupos_biologicos', 'numero_individuos', 'numero_morfoespecies',
            'numero_lotes', 'texto_sha256', 'firma_declarada',
            'margen_clasificacion', 'requiere_confirmacion_humana', 'evidencias_campos',
        ]));

        $sha256 = $almacenamiento->sha256($ruta);
        $existente = DB::table('recepciones.documentos_regulatorios')
            ->where('solicitud_id', $this->solicitudId)
            ->where('tipo_esperado', $tipoEsperado)
            ->where('sha256', $sha256)
            ->first();

        $version = $existente?->version ?? ((int) DB::table('recepciones.documentos_regulatorios')
            ->where('solicitud_id', $this->solicitudId)
            ->where('tipo_esperado', $tipoEsperado)
            ->max('version') + 1);

        $registro = [
            'id' => $existente?->id ?? (string) Str::uuid(),
            'solicitud_id' => $this->solicitudId,
            'tipo_esperado' => $tipoEsperado,
            'version' => $version,
            'tipo_detectado' => $analisis['tipo_detectado'] ?? AnalizadorDocumentoAmbiental::DESCONOCIDO,
            'nombre_original' => $nombreOriginal ?? $nombre,
            'ruta' => $ruta,
            'sha256' => $sha256,
            'motor_ocr' => $detalleDocumento['motor'] ?? null,
            'confianza' => $analisis['confianza'] ?? 0,
            'numero_documento' => $analisis['numero_documento'] ?? null,
            'numero_autorizacion_relacionada' => $analisis['numero_autorizacion'] ?? null,
            'titular' => $analisis['titular'] ?? null,
            'organizacion' => $analisis['organizacion'] ?? null,
            'ruc' => $analisis['ruc'] ?? null,
            'proyecto' => $analisis['proyecto'] ?? null,
            'emitido_en' => $analisis['emitido_en'] ?? null,
            'valido_desde' => $analisis['valido_desde'] ?? null,
            'valido_hasta' => $analisis['valido_hasta'] ?? null,
            'estado_validacion' => $analisis['estado'] ?? 'rechazado',
            'contenido_extraido' => json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'indicadores' => json_encode($analisis['puntajes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'errores' => json_encode($analisis['errores'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'advertencias' => json_encode($analisis['advertencias'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];

        if ($existente !== null) {
            unset($registro['id'], $registro['created_at']);
            DB::table('recepciones.documentos_regulatorios')->where('id', $existente->id)->update($registro);

            return;
        }

        DB::table('recepciones.documentos_regulatorios')->insert($registro);
    }

}
