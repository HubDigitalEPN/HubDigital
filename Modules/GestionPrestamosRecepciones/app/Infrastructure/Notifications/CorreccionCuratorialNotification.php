<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Resume al depositante los datos de formato que curaduría ajustó en su matriz.
 *
 * Se envía una sola vez, al aprobarse la solicitud, y no una por celda: el objetivo es
 * que se entere de lo que se tocó, no llenarle el buzón. El depositante firmó esa
 * matriz al enviarla, así que los cambios posteriores no pueden quedar en silencio.
 */
final class CorreccionCuratorialNotification extends Notification
{
    /**
     * @param  list<array{campo: string, anterior: mixed, nuevo: mixed, especie: string}>  $correcciones
     */
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly array $correcciones,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $n = count($this->correcciones);

        return (new MailMessage)
            ->subject($n === 1
                ? 'Curaduría ajustó un dato de tu matriz'
                : "Curaduría ajustó {$n} datos de tu matriz")
            ->view('gestionprestamosrecepciones::mails.correccion-curatorial', [
                'numero' => $this->numero,
                'correcciones' => $this->correcciones,
                'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $n = count($this->correcciones);

        return [
            'tipo' => 'correccion_curatorial',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => $n === 1
                ? 'Curaduría ajustó un dato de formato de tu solicitud '.($this->numero ?? '').'. No se modificó la identificación de las especies.'
                : 'Curaduría ajustó '.$n.' datos de formato de tu solicitud '.($this->numero ?? '').'. No se modificó la identificación de las especies.',
            'url' => route('prestamos.investigador.deposito.detalle', $this->solicitudId),
            'icono' => 'pencil-square',
        ];
    }
}
