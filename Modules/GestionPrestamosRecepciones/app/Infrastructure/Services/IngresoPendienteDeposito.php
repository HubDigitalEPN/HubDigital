<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\CodigoQrDisponibleNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;

/** Outbox durable. La aprobación no depende de PDF, R2, SMTP ni del despacho de la cola. */
final class IngresoPendienteDeposito
{
    public const TABLA = 'recepciones.ingreso_pendientes';

    public function registrar(string $id): string
    {
        $s = Solicitud::findOrFail($id);
        abort_unless($s->estado === 'Aprobada Documentalmente' && filled($s->codigo_qr), 409);
        $referencia = (string) Str::uuid();
        DB::table(self::TABLA)->insertOrIgnore([
            'id' => $referencia, 'solicitud_id' => $id,
            'snapshot' => json_encode($s->attributesToArray(), JSON_THROW_ON_ERROR),
            'estado' => 'pendiente', 'correo_estado' => 'pendiente', 'intentos' => 0,
            'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
        ]);

        return (string) DB::table(self::TABLA)->where('solicitud_id', $id)->value('id');
    }

    public function procesar(string $id): void
    {
        $lease = (string) Str::uuid();
        $fila = DB::transaction(function () use ($id, $lease) {
            $r = DB::table(self::TABLA)->where('solicitud_id', $id)->lockForUpdate()->first();
            if (! $r || ! in_array($r->estado, ['pendiente', 'procesando'], true)
                || ($r->ocupado_hasta && now()->lt($r->ocupado_hasta))
                || ($r->reintentar_en && now()->lt($r->reintentar_en))) {
                return null;
            }
            // Una caída después de hablar con SMTP tiene resultado incierto. No duplicar envíos a ciegas.
            if ($r->correo_estado === 'enviando') {
                DB::table(self::TABLA)->where('id', $r->id)->update(['estado' => 'revision_necesaria', 'ultimo_error' => 'resultado_correo_incierto', 'updated_at' => now()->toIso8601String()]);

                return null;
            }
            if ($r->intentos >= 3) {
                DB::table(self::TABLA)->where('id', $r->id)->update(['estado' => 'fallido', 'updated_at' => now()->toIso8601String()]);

                return null;
            }
            DB::table(self::TABLA)->where('id', $r->id)->update([
                'lease' => $lease, 'estado' => 'procesando', 'ocupado_hasta' => now()->addMinutes(6)->toIso8601String(),
                'intentos' => $r->intentos + 1, 'updated_at' => now()->toIso8601String(),
            ]);

            return $r;
        });
        if (! $fila) {
            return;
        }
        $actualizar = fn (array $datos) => DB::table(self::TABLA)->where('id', $fila->id)->where('lease', $lease)->update([...$datos, 'updated_at' => now()->toIso8601String()]);
        try {
            $s = Solicitud::findOrFail($id);
            if ($s->estado !== 'Aprobada Documentalmente' || $s->codigo_qr !== json_decode($fila->snapshot, true, flags: JSON_THROW_ON_ERROR)['codigo_qr']) {
                $actualizar(['estado' => 'cancelado', 'ocupado_hasta' => null]);

                return;
            }
            app(DocumentoIngresoDeposito::class)->contenido($id);
            $usuario = User::findOrFail($s->investigador_id);
            $notificacion = new CodigoQrDisponibleNotification($id, $s->numero, $s->codigo_qr, $s->tipo_tramite, sinCorreo: true, leaseIngreso: $lease);
            $notificacion->id = $fila->id;
            if (! $fila->campana_encolada_en) {
                $usuario->notify($notificacion);
                $actualizar(['campana_encolada_en' => now()->toIso8601String()]);
            }
            if ($fila->correo_estado !== 'aceptado_transporte') {
                $actualizar(['correo_estado' => 'enviando']);
                Notification::sendNow($usuario, $notificacion, ['mail']);
                if (DB::table(self::TABLA)->where('id', $fila->id)->value('correo_estado') !== 'aceptado_transporte') {
                    $actualizar(['estado' => 'revision_necesaria', 'correo_estado' => 'no_confirmado', 'ocupado_hasta' => null, 'ultimo_error' => 'correo_sin_confirmacion_transporte']);

                    return;
                }
            }
            $actualizar(['estado' => 'completado', 'ocupado_hasta' => null, 'ultimo_error' => null]);
        } catch (\Throwable $e) {
            $correo = DB::table(self::TABLA)->where('id', $fila->id)->value('correo_estado');
            $actualizar([
                'estado' => $correo === 'enviando' ? 'revision_necesaria' : ($fila->intentos >= 2 ? 'fallido' : 'pendiente'),
                'ocupado_hasta' => null, 'reintentar_en' => now()->addMinutes(2)->toIso8601String(),
                'despachado_en' => null,
                // No persistir respuestas de proveedores ni datos personales en el diagnóstico.
                'ultimo_error' => class_basename($e),
            ]);
            report($e);
        }
    }

    public function reintentar(string $id, User $curador, string $motivo, bool $aceptarDuplicado = false): void
    {
        abort_unless($curador->tieneAlgunRol(RolUsuario::CURADOR, RolUsuario::ADMIN), 403);
        if (trim($motivo) === '' || mb_strlen($motivo) > 3000) {
            throw new \DomainException('Explica el motivo del reintento.');
        }
        DB::transaction(function () use ($id, $curador, $motivo, $aceptarDuplicado): void {
            $s = Solicitud::lockForUpdate()->findOrFail($id);
            abort_unless($s->estado === 'Aprobada Documentalmente', 409);
            $r = DB::table(self::TABLA)->where('solicitud_id', $id)->lockForUpdate()->first();
            abort_unless($r && in_array($r->estado, ['fallido', 'revision_necesaria'], true), 409);
            abort_if($r->estado === 'revision_necesaria' && ! $aceptarDuplicado, 409, 'Confirma que verificaste el resultado incierto y aceptas el posible duplicado.');
            $historial = json_decode($r->historial_reintentos, true, flags: JSON_THROW_ON_ERROR);
            $historial[] = ['actor_id' => (string) $curador->id, 'fecha_utc' => now()->toIso8601String(), 'motivo' => trim($motivo), 'estado_previo' => $r->estado, 'acepta_posible_duplicado' => $aceptarDuplicado];
            DB::table(self::TABLA)->where('id', $r->id)->update([
                'estado' => 'pendiente', 'correo_estado' => $r->correo_estado === 'aceptado_transporte' ? $r->correo_estado : 'pendiente',
                'intentos' => 0, 'lease' => null, 'ocupado_hasta' => null, 'reintentar_en' => null,
                'despachado_en' => null,
                'historial_reintentos' => json_encode($historial, JSON_THROW_ON_ERROR), 'updated_at' => now()->toIso8601String(),
            ]);
        });
    }
}
