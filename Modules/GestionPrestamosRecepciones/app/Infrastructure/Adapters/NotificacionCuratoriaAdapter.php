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
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\LoteRecibidoParaActaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\NuevaSolicitudPorRevisarNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Adaptador de notificaciones a la curaduría. Entrega por correo y por el portal
 * (campana) a todas las cuentas con facultades CURADOR o ADMIN.
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

    public function notificarLoteRecibidoParaActa(
        string $solicitudId,
        string $receptorId,
        bool $conObservaciones,
    ): string {
        $referencia = (string) Str::uuid();
        $deposito = SolicitudDepositoEloquentModel::find($solicitudId);
        $curadores = $this->equipoCuratorial()
            ->when(
                $deposito?->curador_responsable,
                fn ($q, string $curadorId) => $q->where('id', $curadorId),
            )
            ->get();

        // Los expedientes antiguos pueden no tener responsable o apuntar a una
        // cuenta ya deshabilitada. En ese caso la alerta no debe perderse.
        if ($curadores->isEmpty() && $deposito?->curador_responsable) {
            Log::warning('Curador responsable no disponible; la alerta se reasigna al equipo', [
                'referencia' => $referencia,
                'solicitud_id' => $solicitudId,
                'curador_responsable' => $deposito->curador_responsable,
            ]);
            $curadores = $this->equipoCuratorial()->get();
        }

        if ($curadores->isEmpty()) {
            Log::warning('Lote recibido sin curadores a quienes asignar el acta', [
                'referencia' => $referencia,
                'solicitud_id' => $solicitudId,
                'receptor_id' => $receptorId,
            ]);

            return $referencia;
        }

        Notification::send($curadores, new LoteRecibidoParaActaNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
            tipoTramite: $deposito?->tipo_tramite,
            nombreReceptor: User::find($receptorId)?->name,
            conObservaciones: $conObservaciones,
        ));

        return $referencia;
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
        $otrosCuradores = $this->equipoCuratorial()
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
        $curadores = $this->equipoCuratorial()->get();
        $destinoQa = app()->environment('local') && config('hubdigital.qa_deposit_id') === $solicitudId
            ? (string) config('hubdigital.qa_mail_to', '') : '';
        if (! filter_var($destinoQa, FILTER_VALIDATE_EMAIL)) $destinoQa = '';

        if ($curadores->isEmpty() && $destinoQa === '') {
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

        $notificacion = new NuevaSolicitudPorRevisarNotification(
            solicitudId: $solicitudId,
            numero: $deposito?->numero,
            tipoTramite: $deposito?->tipo_tramite,
            nombreInvestigador: $nombreInvestigador,
            fechaEnvio: $deposito?->updated_at !== null
                ? \Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador::formatear($deposito->updated_at) : null,
            esReenvio: $esReenvio,
        );
        Notification::sendNow($curadores, $notificacion, ['database']);
        if ($destinoQa !== '') {
            Notification::route('mail', $destinoQa)->notify($notificacion->soloCorreo());
        } else {
            Notification::send($curadores, $notificacion->canalesExternos());
        }

        return $referencia;
    }

    private function equipoCuratorial(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()->whereHas(
            'roles',
            fn ($consulta) => $consulta->whereIn('rol', [
                RolUsuario::CURADOR->value,
                RolUsuario::ADMIN->value,
            ]),
        );
    }
}
