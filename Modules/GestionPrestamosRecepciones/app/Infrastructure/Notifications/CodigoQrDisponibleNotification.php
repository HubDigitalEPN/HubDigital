<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al depositante que su solicitud fue aprobada documentalmente y que su
 * Código QR de lote está disponible para la entrega física. Se envía por correo y
 * por el portal (campana).
 */
final class CodigoQrDisponibleNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \App\Notifications\CanalesCampana;
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly string $codigoQR,
        public readonly string $tipoTramite,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->canalesCampana($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pdf = app(\Modules\GestionPrestamosRecepciones\Infrastructure\Services\DocumentoIngresoDeposito::class)->contenido($this->solicitudId);
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
