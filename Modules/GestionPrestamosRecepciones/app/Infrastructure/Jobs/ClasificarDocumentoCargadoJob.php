<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DocumentoDepositoRechazado;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ValidacionPreviaDocumentoDeposito;

/** Revisa archivos históricos o vigentes sin comprobación previa; no acepta nuevas cargas. */
final class ClasificarDocumentoCargadoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        private readonly string $solicitudId,
        private readonly string $nombre,
        private readonly string $ruta,
    ) {
        if (config('hubdigital.validation_mode')) {
            $this->onQueue((string) config('hubdigital.validation_queue'));
        }
    }

    public function handle(ValidacionPreviaDocumentoDeposito $revision, AlmacenamientoDepositos $almacenamiento): void
    {
        $modelo = SolicitudDepositoEloquentModel::query()->find($this->solicitudId);
        if (($modelo?->documentos_cargados[$this->nombre] ?? null) !== $this->ruta
            || ($modelo?->validacion_archivos[$this->nombre] ?? null) !== 'analizando') {
            return;
        }
        $provincia = $modelo->provincia_origen;
        $copia = $almacenamiento->copiaLocal($this->ruta);
        try {
            $resultado = $revision->validar($copia->ruta(), $this->nombre);
            $estado = 'valido';
        } catch (DocumentoDepositoRechazado $error) {
            $resultado = ['mensaje' => $error->getMessage()];
            $estado = $error->estado;
        } finally {
            $copia->limpiar();
        }
        $token = DB::transaction(function () use ($resultado, $estado, $provincia): ?string {
            $vigente = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)->lockForUpdate()->first();
            if (($vigente?->documentos_cargados[$this->nombre] ?? null) !== $this->ruta
                || $vigente?->provincia_origen !== $provincia
                || ($vigente?->validacion_archivos[$this->nombre] ?? null) !== 'analizando') {
                return null;
            }
            $token = (string) Str::uuid();
            $validaciones = $vigente->validacion_archivos ?? [];
            $firmas = $vigente->firmas_electronicas ?? [];
            $previas = $vigente->validacion_previa_documentos ?? [];
            $validaciones[$this->nombre] = $estado;
            unset($firmas[$this->nombre]);
            $previas[$this->nombre] = [...$resultado, 'ruta' => $this->ruta, 'verificacion_id' => $token,
                'firma_verificada' => false, 'comprobado_en' => now()->toIso8601String()];
            if ($estado === 'valido') {
                $firmas[$this->nombre] = 'validando';
            }
            $vigente->forceFill(['validacion_archivos' => $validaciones,
                'firmas_electronicas' => $firmas, 'validacion_previa_documentos' => $previas])->save();

            return $estado === 'valido' ? $token : null;
        });
        if ($token !== null) {
            try {
                VerificarFirmaDocumentoJob::dispatch($this->solicitudId, $this->nombre, $this->ruta, $token);
            } catch (\Throwable $error) {
                report($error);
                (new VerificarFirmaDocumentoJob($this->solicitudId, $this->nombre, $this->ruta, $token))->failed($error);
            }
        }
    }

    public function failed(\Throwable $error): void
    {
        report($error);
        DB::transaction(function (): void {
            $modelo = SolicitudDepositoEloquentModel::query()->whereKey($this->solicitudId)->lockForUpdate()->first();
            if (($modelo?->documentos_cargados[$this->nombre] ?? null) !== $this->ruta
                || ($modelo?->validacion_archivos[$this->nombre] ?? null) !== 'analizando') {
                return;
            }
            $estados = $modelo->validacion_archivos ?? [];
            $estados[$this->nombre] = 'revision_fallida';
            $modelo->forceFill(['validacion_archivos' => $estados])->save();
        });
    }
}
