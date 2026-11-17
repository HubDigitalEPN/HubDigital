<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\IniciarRecepcionLote\IniciarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\SolicitudRechazadaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel as Solicitud;

final class RecepcionQrDeposito
{
    public function decidir(string $id, string $codigo, User $tecnico, bool $aprobar, string $motivo = ''): void
    {
        abort_unless($tecnico->esReceptor(), 403);
        DB::transaction(function () use ($id, $codigo, $tecnico, $aprobar, $motivo): void {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($id);
            abort_unless(hash_equals((string) $solicitud->codigo_qr, $codigo), 403);
            abort_unless($solicitud->estado === 'Aprobada Documentalmente', 409, 'La aprobación documental de este ingreso no está vigente.');
            abort_if($solicitud->recepcion_qr_rechazada_en, 409, 'La recepción fue rechazada.');
            if ($solicitud->recepcion_qr_en) {
                abort_unless($aprobar, 409, 'El material ya fue recibido.');
                return;
            }
            abort_if(DB::table('recepciones.recepcion_lotes')->where('solicitud_deposito_id', $id)->exists(), 409, 'Este lote ya tiene una recepción registrada. Consulta su expediente.');
            if ($aprobar) {
                app(IniciarRecepcionLoteHandler::class)(new IniciarRecepcionLoteInput($id, (string) $tecnico->id));
                $solicitud->forceFill(['recepcion_qr_en' => now(), 'recepcion_qr_por' => $tecnico->id])->save();
            } else {
                $motivo = trim($motivo);
                if ($motivo === '' || mb_strlen($motivo) > 3000) throw new \DomainException('Escribe el motivo de rechazo (máximo 3000 caracteres).');
                $solicitud->forceFill([
                    'estado' => 'Rechazo Permanente', 'rechazada_en' => now(),
                    'comentario_curador' => $motivo, 'recepcion_qr_motivo' => $motivo,
                    'recepcion_qr_rechazada_en' => now(), 'recepcion_qr_por' => $tecnico->id,
                ])->save();
                User::find($solicitud->investigador_id)?->notify(new SolicitudRechazadaNotification($id, $solicitud->numero, $motivo));
            }
        });
    }
}
