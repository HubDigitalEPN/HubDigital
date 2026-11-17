<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use App\Notifications\CanalesCampana;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\DocumentoIngresoDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\IngresoPendienteDeposito;

/**
 * Notifica al depositante que su solicitud fue aprobada documentalmente y que su
 * Código QR de lote está disponible para la entrega física. Se envía por correo y
 * por el portal (campana).
 */
final class CodigoQrDisponibleNotification extends Notification implements ShouldQueue
{
    use CanalesCampana;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $channel !== 'database' || ! DB::table('notifications')->where('id', $this->id)->exists();
    }

    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly string $codigoQR,
        public readonly string $tipoTramite,
        public readonly bool $sinCorreo = false,
        public readonly ?string $leaseIngreso = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_filter($this->canalesCampana($notifiable), fn ($canal) => ! $this->sinCorreo || $canal !== 'mail'));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pdf = app(DocumentoIngresoDeposito::class)->contenido($this->solicitudId);

        return (new MailMessage)
            ->attachData($pdf, 'Ingreso-'.($this->numero ?? $this->solicitudId).'.pdf', ['mime' => 'application/pdf'])
            ->subject('Tu solicitud de '.mb_strtolower($this->tipoTramite).' fue aprobada')
            ->view('gestionprestamosrecepciones::mails.deposito-aprobado', [
                'numero' => $this->numero,
                'codigoQR' => $this->codigoQR,
                'tipoTramite' => $this->tipoTramite,
                'url' => route('depositos.ingreso.descargar', $this->solicitudId),
            ]);
    }

    /** Solo una respuesta efectiva del transporte acredita el envío, no su invocación. */
    public function afterSending(object $notifiable, string $channel, mixed $response): void
    {
        if ($channel !== 'mail' || ! $response instanceof SentMessage || $this->leaseIngreso === null) {
            return;
        }
        DB::table(IngresoPendienteDeposito::TABLA)
            ->where('id', $this->id)->where('solicitud_id', $this->solicitudId)->where('lease', $this->leaseIngreso)
            ->where('correo_estado', 'enviando')->update([
                'correo_estado' => 'aceptado_transporte', 'correo_aceptado_en' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'deposito_aprobado',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'Tu solicitud '.($this->numero ?? '').' fue aprobada. Descarga el documento de ingreso con QR y preséntalo con el material físico.',
            'url' => route('depositos.ingreso.descargar', $this->solicitudId),
            'icono' => 'check-badge',
        ];
    }
}
