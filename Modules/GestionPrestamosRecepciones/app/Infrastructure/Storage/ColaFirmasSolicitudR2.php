<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\PersistirFirmaSolicitudR2;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/** Outbox cifrada: el PDF y su trabajo quedan comprometidos en la misma transacción. */
final class ColaFirmasSolicitudR2
{
    public const TABLA = 'recepciones.firmas_r2_pendientes';

    public function encolar(ArchivoLocalDeposito $archivo, SolicitudDepositoEloquentModel $solicitud,
        string $ruta, ?string $rutaAnterior): string
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
        $id = (string) Str::uuid();
        DB::table(self::TABLA)->insert([
            'id' => $id, 'solicitud_id' => $solicitud->id,
            'documento_version' => $solicitud->solicitud_documento_version,
            'ruta' => $ruta, 'ruta_anterior' => $rutaAnterior, 'sha256' => $huella,
            'bytes' => strlen($contenido), 'contenido_cifrado' => Crypt::encryptString($contenido),
            'estado' => 'pendiente', 'intentos' => 0, 'created_at' => now(), 'updated_at' => now(),
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
        return $solicitud !== null && $solicitud->solicitud_firmada_en !== null
            && (int) $solicitud->solicitud_documento_version === (int) $registro->documento_version
            && $solicitud->solicitud_firmada_ruta === $registro->ruta
            && hash_equals((string) $solicitud->solicitud_firmada_sha256, $registro->sha256);
    }

    /** Reintentos idempotentes. Ningún fallo descarta los bytes pendientes. */
    public function entregar(string $id, AlmacenamientoDepositos $almacenamiento): void
    {
        DB::table(self::TABLA)->where('id', $id)->where('estado', 'pendiente')->increment('intentos');
        try {
            DB::transaction(function () use ($id, $almacenamiento): void {
                // Sólo esta fila permanece bloqueada durante la transferencia.
                // El wizard puede avanzar; descartar espera este bloqueo y retira todo después.
                $registro = DB::table(self::TABLA)->where('id', $id)->lockForUpdate()->first();
                if ($registro === null || $registro->estado === 'cancelado') return;
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
                $huella = $almacenamiento->guardarContenido($registro->ruta, $contenido, 'application/pdf', true);
                if (!hash_equals($registro->sha256, $huella)) throw new \RuntimeException('R2 no confirmó el PDF íntegro.');
                $confirmado = DB::table(self::TABLA)->where('id', $id)->whereExists(function ($consulta) use ($registro): void {
                    $consulta->selectRaw('1')->from('recepciones.solicitudes_deposito as vigente')
                        ->where('vigente.id', $registro->solicitud_id)
                        ->where('vigente.solicitud_documento_version', $registro->documento_version)
                        ->where('vigente.solicitud_firmada_ruta', $registro->ruta)
                        ->where('vigente.solicitud_firmada_sha256', $registro->sha256)
                        ->whereNotNull('vigente.solicitud_firmada_en');
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
    public function cancelarSolicitud(string $solicitudId): array
    {
        if (!Schema::hasTable(self::TABLA)) return [];
        $registros = DB::table(self::TABLA)->where('solicitud_id', $solicitudId)->lockForUpdate()->get();
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
