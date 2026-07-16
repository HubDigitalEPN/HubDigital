<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionCuratoriaPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\DecisionDocumentalCuradorNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\NuevaSolicitudPorRevisarNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Adaptador de notificaciones a la curaduría. Entrega por correo y por el portal
 * (campana) a todos los usuarios con rol CURADOR.
 */
final class NotificacionCuratoriaAdapter implements NotificacionCuratoriaPort
{
    public function notificarIntervencionRequerida(string $solicitudId, string $investigadorId): string
    {
        return $this->notificarCuradores($solicitudId, 'Intervención requerida');
    }

    public function notificarNuevaSolicitudPorRevisar(string $solicitudId): string
    {
        return $this->notificarCuradores($solicitudId, 'Nueva solicitud por revisar');
    }

    public function notificarDecisionDocumentalAOtrosCuradores(
        string $solicitudId,
        string $curadorQueDecideId,
        string $decision,
        ?string $motivo = null,
    ): string {
        $referencia = (string) Str::uuid();

        $deposito = SolicitudDepositoEloquentModel::find($solicitudId);

        // Se notifica a todos los curadores EXCEPTO al que tomó la decisión.
        $otrosCuradores = User::where('rol', RolUsuario::CURADOR)
            ->where('id', '!=', $curadorQueDecideId)
            ->get();

        if ($otrosCuradores->isEmpty()) {
            Log::info('Notificación de decisión a otros curadores omitida: no hay otros curadores', [
                'referencia' => $referencia, 'solicitud_id' => $solicitudId, 'decision' => $decision,
            ]);

            return $referencia;
        }

        $curadorDecide = User::find($curadorQueDecideId);

        Notification::send($otrosCuradores, new DecisionDocumentalCuradorNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
            tipoTramite: $deposito?->tipo_tramite,
            decision: $decision,
            motivo: $motivo,
            nombreCuradorDecide: $curadorDecide?->name,
        ));

        return $referencia;
    }

    /**
     * Notifica a todos los curadores de forma defensiva: si no hay curadores,
     * registra el evento y devuelve igualmente una referencia no vacía para
     * preservar el contrato del puerto.
     */
    private function notificarCuradores(string $solicitudId, string $contexto): string
    {
        $referencia = (string) Str::uuid();

        $deposito = SolicitudDepositoEloquentModel::find($solicitudId);
        $curadores = User::where('rol', RolUsuario::CURADOR)->get();

        if ($curadores->isEmpty()) {
            Log::info('Notificación a curaduría omitida: no hay curadores', [
                'referencia' => $referencia, 'contexto' => $contexto, 'solicitud_id' => $solicitudId,
            ]);

            return $referencia;
        }

        $investigador = $deposito ? User::find($deposito->investigador_id) : null;
        $nombreInvestigador = $investigador?->name
            ?? $deposito?->nombre_investigador_documento;

        // Es un reenvío tras corrección si la solicitud ya había sido devuelta antes.
        $esReenvio = $deposito?->rechazada_en !== null;

        Notification::send($curadores, new NuevaSolicitudPorRevisarNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
            tipoTramite: $deposito?->tipo_tramite,
            nombreInvestigador: $nombreInvestigador,
            fechaEnvio: $deposito?->updated_at?->format('d/m/Y H:i'),
            esReenvio: $esReenvio,
        ));

        return $referencia;
    }
}
