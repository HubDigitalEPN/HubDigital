<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Un trabajo por canal: un error de correo o push no pierde la campana. */
trait CanalesCampana
{
    use Queueable;

    protected function canalesCampana(object $destinatario, array $canales = ['mail', 'database']): array
    {
        $this->afterCommit();
        // Conserva el reenvío específico soloCorreo sin duplicar otros canales.
        $campana = in_array('database', $canales, true);
        if (! $campana && ! in_array(WebPushChannel::class, $canales, true)) return $canales;
        $canales = array_values(array_unique([...array_diff($canales, [WebPushChannel::class]), 'mail']));
        if (filled(config('webpush.vapid.subject')) && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key')) && method_exists($destinatario, 'pushSubscriptions')
            && $destinatario->pushSubscriptions()->exists()) $canales[] = WebPushChannel::class;
        return array_values(array_unique($canales));
    }

    public function toMail(object $destinatario): MailMessage
    {
        $datos = $this->toArray($destinatario);
        return (new MailMessage)->subject('HubDigital · Actualización de tu trámite')
            ->greeting('Hola'.(filled($destinatario->name ?? null) ? ', '.$destinatario->name : '').':')
            ->line((string) ($datos['mensaje'] ?? 'Tienes una actualización disponible en HubDigital.'))
            ->action('Consultar en HubDigital', $datos['url'] ?? route('dashboard'))
            ->line('Laboratorio de Invertebrados · Escuela Politécnica Nacional');
    }

    public function toWebPush(object $destinatario): WebPushMessage
    {
        return $this->mensajeWebPushCampana($destinatario);
    }

    protected function mensajeWebPushCampana(object $destinatario): WebPushMessage
    {
        $datos = $this->toArray($destinatario);
        $tipo = $datos['tipo'] ?? '';
        [$titulo, $resumen] = match ($tipo) {
            'nueva_solicitud_revisar' => ['Nueva solicitud', 'Hay una solicitud pendiente de revisión documental.'],
            'solicitud_corregida_reenviada' => ['Solicitud corregida', 'Se recibió una corrección para revisión documental.'],
            'lote_recibido_acta_pendiente' => ['Acta pendiente', 'Un depósito recibido requiere revisión y firma del acta.'],
            'entrega_fisica_anunciada' => ['Entrega programada', 'Se anunció una entrega física. Consulta la fecha en el expediente.'],
            'deposito_aprobado' => ['Solicitud aprobada', 'Tu solicitud fue aprobada. Consulta las instrucciones de entrega en HubDigital.'],
            'deposito_rechazado', 'solicitud_rechazada_por_curador' => ['Revisión requerida', 'Tu solicitud tiene una decisión documental. Consulta el detalle en HubDigital.'],
            'solicitud_aprobada_por_curador' => ['Revisión completada', 'Se completó la revisión documental de una solicitud.'],
            'acta_recepcion_firmada' => ['Acta firmada', 'El acta de recepción firmada está disponible en tu expediente.'],
            'recepcion_constatada' => ['Recepción registrada', 'Se registró la recepción del material. Consulta el detalle en HubDigital.'],
            'recepcion_con_observaciones', 'correccion_curatorial', 'orden_accion_correctiva' => ['Acción pendiente', 'Tu expediente requiere atención. Revisa las indicaciones en HubDigital.'],
            default => ['Actualización de trámite', 'Tienes una actualización en tu expediente. Entra a HubDigital para consultar el detalle.'],
        };
        $evento = (string) ($datos['eventoId'] ?? $this->id ?? ($tipo.'-'.($datos['solicitudId'] ?? 'aviso')));
        return (new WebPushMessage)->title('HubDigital · '.$titulo)->body($resumen)
            ->icon('/images/hub-icon.png')->badge('/images/hub-icon.png')->tag($evento)
            ->data(['notificationId' => $evento, 'url' => $datos['url'] ?? route('dashboard')])
            ->options(['TTL' => 86400, 'urgency' => ($datos['prioridad'] ?? '') === 'alta' ? 'high' : 'normal']);
    }
}
