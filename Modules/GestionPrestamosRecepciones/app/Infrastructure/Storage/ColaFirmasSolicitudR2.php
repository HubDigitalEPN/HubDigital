<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\PersistirFirmaSolicitudR2;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\RetirarDocumentoSolicitudR2;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/** Outbox cifrada: el PDF y su trabajo quedan comprometidos en la misma transacción. */
final class ColaFirmasSolicitudR2
{
    public const TABLA = 'recepciones.firmas_r2_pendientes';

    public function encolar(ArchivoLocalDeposito $archivo, SolicitudDepositoEloquentModel $solicitud,
        string $ruta, ?string $rutaAnterior): string
    {
        $this->exigirTransaccionDurable();
        $contenido = file_get_contents($archivo->ruta());
        if ($contenido === false || strlen($contenido) > (int) config('deposit-storage.max_object_bytes', 26214400)) {
            throw new \RuntimeException('No se pudo conservar íntegramente el PDF firmado.');
        }
        $huella = hash('sha256', $contenido);
        if ($archivo->dictamenFirma === null) {
            app(ValidadorPdfDeposito::class)->validar($archivo->ruta());
        } elseif (($archivo->dictamenFirma['aceptable'] ?? false) !== true
            || ($archivo->dictamenFirma['pdf_seguro'] ?? false) !== true
            || !hash_equals((string) ($archivo->dictamenFirma['pdf_sha256'] ?? ''), $huella)) {
            throw new \InvalidArgumentException('El PDF no corresponde al dictamen validado.');
        }
        if (!hash_equals((string) $solicitud->solicitud_firmada_sha256, $huella)) {
            throw new \InvalidArgumentException('La copia pendiente no corresponde a la firma persistida.');
        }
        return $this->insertar($solicitud, $ruta, $rutaAnterior, $contenido, $huella);
    }

    private function exigirTransaccionDurable(): void
    {
        if (DB::transactionLevel() < 1
            || config('queue.connections.r2_signatures.driver') !== 'database'
            || config('queue.connections.r2_signatures.connection') !== config('database.default')) {
            throw new \RuntimeException('La firma y su trabajo requieren la misma transacción de PostgreSQL.');
        }
        if (!config('deposit-storage.verify_after_write', true)) {
            throw new \RuntimeException('La cola de firmas requiere la verificación de integridad de R2.');
        }
        if (app(AlmacenamientoDepositos::class)->driver() !== 'r2') {
            throw new \RuntimeException('La cola de firmas requiere almacenamiento R2.');
        }
    }

    /** La admisión del servidor y la firma se vinculan a los mismos bytes, nunca a datos del navegador. */
    public function encolarDocumento(string $archivo, SolicitudDepositoEloquentModel $solicitud,
        string $nombre, ?string $rutaAnterior): string
    {
        $this->exigirTransaccionDurable();
        $previa = $solicitud->validacion_previa_documentos[$nombre] ?? [];
        $ruta = $solicitud->documentos_cargados[$nombre] ?? null;
        $contenido = file_get_contents($archivo);
        if ($contenido === false || $contenido === '' || strlen($contenido) > 20 * 1024 * 1024
            || strlen($contenido) > (int) config('deposit-storage.max_object_bytes', 26214400)
            || ($previa['estructura']['status'] ?? null) !== 'seguro'
            || !is_string($ruta) || ($previa['ruta'] ?? null) !== $ruta
            || !Str::isUuid($previa['verificacion_id'] ?? '')
            || !in_array($nombre, $solicitud->documentos_requeridos ?? [], true)
            || !hash_equals((string) ($previa['sha256'] ?? ''), hash('sha256', $contenido))) {
            throw new \InvalidArgumentException('La copia pendiente no corresponde al documento inspeccionado.');
        }
        return $this->insertar($solicitud, $ruta, $rutaAnterior, $contenido, $previa['sha256'], $nombre, $previa['verificacion_id']);
    }

    private function insertar(SolicitudDepositoEloquentModel $solicitud, string $ruta, ?string $rutaAnterior,
        string $contenido, string $huella, ?string $nombre = null, ?string $verificacionId = null): string
    {
        $id = (string) Str::uuid();
        DB::table(self::TABLA)->insert([
            'id' => $id, 'solicitud_id' => $solicitud->id,
            'documento_version' => $solicitud->solicitud_documento_version,
            'ruta' => $ruta, 'ruta_anterior' => $rutaAnterior, 'sha256' => $huella,
            'bytes' => strlen($contenido), 'contenido_cifrado' => Crypt::encryptString($contenido),
            'estado' => 'pendiente', 'intentos' => 0, 'created_at' => now(), 'updated_at' => now(),
            'nombre_documento' => $nombre, 'verificacion_id' => $verificacionId,
        ]);
        PersistirFirmaSolicitudR2::dispatch($id)->onConnection('r2_signatures')->onQueue('r2-signatures')->beforeCommit();
        return $id;
    }

    public function registro(SolicitudDepositoEloquentModel $solicitud): ?object
    {
        $id = $solicitud->solicitud_firma_metadata['r2_pendiente_id'] ?? null;
        if (!is_string($id)) return null;
        return DB::table(self::TABLA)->where('id', $id)->where('solicitud_id', $solicitud->id)
            ->where('documento_version', $solicitud->solicitud_documento_version)
            ->where('ruta', $solicitud->solicitud_firmada_ruta)
            ->where('sha256', $solicitud->solicitud_firmada_sha256)->first();
    }

    public function contenidoPendiente(SolicitudDepositoEloquentModel $solicitud): ?string
    {
        $registro = $this->registro($solicitud);
        if ($registro === null || $registro->estado === 'confirmado') return null;
        if ($registro->estado !== 'pendiente') throw new \RuntimeException('Esta firma ya no está vigente.');
        return $this->descifrar($registro);
    }

    public function disponible(SolicitudDepositoEloquentModel $solicitud): bool
    {
        $registro = $this->registro($solicitud);
        if ($registro === null) return false;
        if ($registro->estado === 'confirmado') return true;
        return $registro->estado === 'pendiente' && $this->descifrar($registro) !== '';
    }

    private function descifrar(object $registro): string
    {
        $contenido = Crypt::decryptString((string) $registro->contenido_cifrado);
        if (strlen($contenido) !== (int) $registro->bytes || !hash_equals($registro->sha256, hash('sha256', $contenido))) {
            throw new \RuntimeException('La copia pendiente no supera la verificación de integridad.');
        }
        return $contenido;
    }

    private function vigente(object $registro, ?SolicitudDepositoEloquentModel $solicitud): bool
    {
        if ($registro->nombre_documento !== null) {
            $previa = $solicitud?->validacion_previa_documentos[$registro->nombre_documento] ?? [];
            return $solicitud !== null
                && (int) $solicitud->solicitud_documento_version === (int) $registro->documento_version
                && ($solicitud->documentos_cargados[$registro->nombre_documento] ?? null) === $registro->ruta
                && ($previa['ruta'] ?? null) === $registro->ruta
                && ($previa['r2_verificacion_id'] ?? null) === $registro->verificacion_id
                && ($previa['r2_pendiente_id'] ?? null) === $registro->id
                && hash_equals((string) ($previa['sha256'] ?? ''), $registro->sha256);
        }
        return $solicitud !== null && $solicitud->solicitud_firmada_en !== null
            && (int) $solicitud->solicitud_documento_version === (int) $registro->documento_version
            && $solicitud->solicitud_firmada_ruta === $registro->ruta
            && hash_equals((string) $solicitud->solicitud_firmada_sha256, $registro->sha256);
    }

    /** Únicamente una copia cifrada vigente de un documento admitido; nunca un archivo local residual. */
    public function documentoPendienteVigente(string $ruta): bool
    {
        if (!Schema::hasTable(self::TABLA)) return false;
        $registro = DB::table(self::TABLA)->where('ruta', $ruta)->where('estado', 'pendiente')
            ->whereNotNull('nombre_documento')
            ->first(['id', 'solicitud_id', 'documento_version', 'ruta', 'sha256', 'nombre_documento', 'verificacion_id']);
        return $registro !== null && $this->vigente($registro, SolicitudDepositoEloquentModel::find($registro->solicitud_id));
    }

    public function contenidoDocumentoPorRuta(string $ruta): ?string
    {
        if (!Schema::hasTable(self::TABLA)) return null;
        $registro = DB::table(self::TABLA)->where('ruta', $ruta)->where('estado', 'pendiente')
            ->whereNotNull('nombre_documento')->first();
        if ($registro === null) return null;
        if (!$this->vigente($registro, SolicitudDepositoEloquentModel::find($registro->solicitud_id))) {
            throw new \RuntimeException('El documento pendiente ya no está vigente.');
        }
        return $this->descifrar($registro);
    }

    /** Reintentos idempotentes. Ningún fallo descarta los bytes pendientes. */
    public function entregar(string $id, AlmacenamientoDepositos $almacenamiento): void
    {
        DB::table(self::TABLA)->where('id', $id)->where('estado', 'pendiente')->increment('intentos');
        try {
            DB::transaction(function () use ($id, $almacenamiento): void {
                // Sólo esta fila permanece bloqueada durante la transferencia.
                // La retirada espera este bloqueo en la cola, nunca en el wizard.
                $registro = DB::table(self::TABLA)->where('id', $id)->lockForUpdate()->first();
                if ($registro === null) return;
                if ($registro->estado === 'cancelado') {
                    if ($registro->nombre_documento !== null) {
                        $almacenamiento->eliminar($registro->ruta);
                        if ($registro->ruta_anterior !== null) $almacenamiento->eliminar($registro->ruta_anterior);
                    }
                    return;
                }
                if ($registro->estado === 'confirmado') {
                    if ($registro->ruta_anterior !== null) {
                        $almacenamiento->eliminar($registro->ruta_anterior);
                        DB::table(self::TABLA)->where('id', $id)->update(['ruta_anterior' => null]);
                    }
                    return;
                }
                $actual = SolicitudDepositoEloquentModel::find($registro->solicitud_id);
                if (!$this->vigente($registro, $actual)) {
                    $almacenamiento->eliminar($registro->ruta);
                    DB::table(self::TABLA)->where('id', $id)->update(['estado' => 'cancelado', 'contenido_cifrado' => null, 'updated_at' => now()]);
                    return;
                }
                $contenido = $this->descifrar($registro);
                // La copia cifrada sigue durable en PostgreSQL; liberar su representación
                // en RAM antes de mantener simultáneamente el PDF y la lectura de R2.
                unset($registro->contenido_cifrado);
                $huella = $almacenamiento->guardarContenido($registro->ruta, $contenido, 'application/pdf', true);
                if (!hash_equals($registro->sha256, $huella)) throw new \RuntimeException('R2 no confirmó el PDF íntegro.');
                $confirmado = DB::table(self::TABLA)->where('id', $id)->whereExists(function ($consulta) use ($registro): void {
                    $consulta->selectRaw('1')->from('recepciones.solicitudes_deposito as vigente')
                        ->where('vigente.id', $registro->solicitud_id)
                        ->where('vigente.solicitud_documento_version', $registro->documento_version);
                    if ($registro->nombre_documento === null) {
                        $consulta->where('vigente.solicitud_firmada_ruta', $registro->ruta)
                            ->where('vigente.solicitud_firmada_sha256', $registro->sha256)
                            ->whereNotNull('vigente.solicitud_firmada_en');
                    } else {
                        $consulta->whereRaw('vigente.documentos_cargados ->> ? = ?', [$registro->nombre_documento, $registro->ruta]);
                        foreach (['ruta' => $registro->ruta, 'sha256' => $registro->sha256,
                            'r2_verificacion_id' => $registro->verificacion_id, 'r2_pendiente_id' => $registro->id] as $campo => $valor) {
                            $consulta->whereRaw('vigente.validacion_previa_documentos -> ? ->> ? = ?', [$registro->nombre_documento, $campo, $valor]);
                        }
                    }
                })->update(['estado' => 'confirmado', 'contenido_cifrado' => null,
                    'confirmado_en' => now(), 'ultimo_error_tipo' => null, 'updated_at' => now()]) === 1;
                if (!$confirmado) {
                    $almacenamiento->eliminar($registro->ruta);
                    DB::table(self::TABLA)->where('id', $id)->update(['estado' => 'cancelado', 'contenido_cifrado' => null, 'updated_at' => now()]);
                } elseif ($registro->ruta_anterior !== null) {
                    $almacenamiento->eliminar($registro->ruta_anterior);
                    DB::table(self::TABLA)->where('id', $id)->update(['ruta_anterior' => null]);
                }
            });
        } catch (\Throwable $error) {
            DB::table(self::TABLA)->where('id', $id)->update(['ultimo_error_tipo' => $error::class, 'updated_at' => now()]);
            throw $error;
        }
    }

    /** El descarte toma este bloqueo después del de la solicitud y antes de borrar R2. */
    public function cancelarDocumento(string $solicitudId, string $nombre, ?array $rutas = null): array
    {
        if (!Schema::hasTable(self::TABLA)) return [];
        $consulta = DB::table(self::TABLA)->where('solicitud_id', $solicitudId)->where('nombre_documento', $nombre)
            ->where('estado', '!=', 'cancelado');
        if ($rutas !== null) $consulta->whereIn('ruta', $rutas);
        $registros = (clone $consulta)->lockForUpdate()->get(['id', 'ruta', 'ruta_anterior']);
        $consulta->update(['estado' => 'cancelado', 'contenido_cifrado' => null, 'updated_at' => now()]);
        foreach ($registros as $registro) {
            // También se reintenta la retirada de una copia que ya estaba confirmada.
            PersistirFirmaSolicitudR2::dispatch($registro->id)->onConnection('r2_signatures')->onQueue('r2-signatures')->beforeCommit();
        }
        return $registros->flatMap(fn ($fila): array => [$fila->ruta, $fila->ruta_anterior])->filter()->unique()->values()->all();
    }

    /** Se captura la versión sin esperar el bloqueo de una transferencia R2 en curso. */
    public function encolarRetiroDocumento(SolicitudDepositoEloquentModel $solicitud, string $nombre): void
    {
        $this->exigirTransaccionDurable();
        $rutas = DB::table(self::TABLA)->where('solicitud_id', $solicitud->id)
            ->where('nombre_documento', $nombre)->where('estado', '!=', 'cancelado')
            ->get(['ruta', 'ruta_anterior'])->flatMap(fn ($fila): array => [$fila->ruta, $fila->ruta_anterior]);
        $rutas = $rutas->push($solicitud->documentos_cargados[$nombre] ?? null)
            ->filter(fn ($ruta): bool => is_string($ruta) && $ruta !== '')->unique()->values()->all();
        if ($rutas !== []) {
            RetirarDocumentoSolicitudR2::dispatch($solicitud->id, $nombre, $rutas)
                ->onConnection('r2_signatures')->onQueue('r2-signatures')->beforeCommit();
        }
    }

    public function cancelarSolicitud(string $solicitudId): array
    {
        if (!Schema::hasTable(self::TABLA)) return [];
        $registros = DB::table(self::TABLA)->where('solicitud_id', $solicitudId)->lockForUpdate()->get(['ruta', 'ruta_anterior']);
        DB::table(self::TABLA)->where('solicitud_id', $solicitudId)->update([
            'estado' => 'cancelado', 'contenido_cifrado' => null, 'updated_at' => now(),
        ]);
        return $registros->flatMap(fn ($fila): array => [$fila->ruta, $fila->ruta_anterior])->filter()->unique()->values()->all();
    }

    public function retirarSolicitud(string $solicitudId): void
    {
        if (!Schema::hasTable(self::TABLA)) return;
        foreach (DB::table(self::TABLA)->where('solicitud_id', $solicitudId)->pluck('id') as $id) {
            DB::table('jobs')->where('queue', 'r2-signatures')->where('payload', 'like', '%'.$id.'%')->delete();
        }
        DB::table(self::TABLA)->where('solicitud_id', $solicitudId)->delete();
    }
}
