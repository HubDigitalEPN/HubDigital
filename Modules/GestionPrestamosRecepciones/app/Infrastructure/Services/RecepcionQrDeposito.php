<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\EstadoEntregaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;

final class RecepcionQrDeposito
{
    public function decidir(string $id, string $codigo, User $tecnico, bool $aprobar, string $motivo = ''): void
    {
        abort_unless(app(\App\Support\AccesoMenu::class)->permite('prestamos.receptor.depositos', 'recibir', $tecnico), 403);
        DB::transaction(function () use ($id, $codigo, $tecnico, $aprobar, $motivo): void {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($id);
            abort_unless(hash_equals((string) $solicitud->codigo_qr, $codigo), 403);
            abort_unless($solicitud->estado === 'Aprobada Documentalmente', 409, 'La aprobación documental de este ingreso no está vigente.');
            abort_if($solicitud->recepcion_qr_rechazada_en, 409, 'La recepción fue rechazada.');
            if ($solicitud->recepcion_qr_en) {
                abort_unless($aprobar, 409, 'El material ya fue recibido.');

                return;
            }
            abort_unless($solicitud->flujoIngreso()->puedeRecibir(), 409, 'El expediente no admite una nueva recepción.');
            abort_if(DB::table('recepciones.recepcion_lotes')->where('solicitud_deposito_id', $id)->exists(), 409, 'Este lote ya tiene una recepción registrada. Consulta su expediente.');
            if ($aprobar) {
                app(IniciarRecepcionLoteHandler::class)(new IniciarRecepcionLoteInput($id, (string) $tecnico->id, desdeQr: true));
                $solicitud->forceFill(['recepcion_qr_en' => now(), 'recepcion_qr_por' => $tecnico->id])->save();
                $this->registrar($solicitud, $tecnico, 'recibida', trim($motivo));
            } else {
                $motivo = trim($motivo);
                if ($motivo === '' || mb_strlen($motivo) > 3000) {
                    throw new \DomainException('Escribe el motivo de rechazo (máximo 3000 caracteres).');
                }
                $solicitud->forceFill([
                    'recepcion_qr_motivo' => $motivo,
                    'recepcion_qr_rechazada_en' => now(), 'recepcion_qr_por' => $tecnico->id,
                ])->save();
                $this->registrar($solicitud, $tecnico, 'no_recibida', $motivo);
                User::find($solicitud->investigador_id)?->notify(new EstadoEntregaNotification($id, 'Entrega no recibida', $motivo));
            }
        });
    }

    public function autorizarNuevoIntento(string $id, User $curador, string $motivo): void
    {
        abort_unless($curador->tieneAlgunRol(RolUsuario::CURADOR), 403);
        if (trim($motivo) === '' || mb_strlen($motivo) > 3000) {
            throw new \DomainException('Escribe el motivo de la autorización (máximo 3000 caracteres).');
        }
        DB::transaction(function () use ($id, $curador, $motivo): void {
            $s = Solicitud::lockForUpdate()->findOrFail($id);
            abort_unless($s->estado === 'Aprobada Documentalmente' && $s->recepcion_qr_rechazada_en && ! $s->recepcionLote, 409);
            $this->registrar($s, $curador, 'nuevo_intento_autorizado', trim($motivo));
            $s->forceFill(['recepcion_qr_rechazada_en' => null, 'recepcion_qr_por' => null, 'recepcion_qr_motivo' => null])->save();
            User::find($s->investigador_id)?->notify(new EstadoEntregaNotification($id, 'Nuevo intento de entrega autorizado', trim($motivo)));
        });
    }

    private function registrar(Solicitud $s, User $actor, string $decision, string $motivo): void
    {
        $historial = $s->historial_entregas ?? [];
        $historial[] = ['decision' => $decision, 'actor_id' => (string) $actor->id, 'fecha_utc' => now()->toIso8601String(), 'motivo' => $motivo];
        $s->forceFill(['historial_entregas' => $historial])->save();
    }
}
