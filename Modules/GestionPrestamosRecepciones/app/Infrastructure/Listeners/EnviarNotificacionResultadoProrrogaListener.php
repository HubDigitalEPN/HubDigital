<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaAprobada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ProrrogaRechazada;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails\ResultadoProrrogaMailable;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

/**
 * Envía al investigador un correo con el resultado de su solicitud de prórroga,
 * tanto si fue aprobada como rechazada por el curador.
 */
final class EnviarNotificacionResultadoProrrogaListener
{
    public function __construct(
        private readonly InvestigadorEmailPort $investigadorEmail,
    ) {}

    public function handle(ProrrogaAprobada|ProrrogaRechazada $event): void
    {
        $prestamoId = (string) $event->prestamoId;

        $prestamo = PrestamoEloquentModel::with('acta')->find($prestamoId);
        $numeroPrestamo = $prestamo?->codigo ?? $prestamo?->acta?->codigo ?? $prestamoId;

        $investigadorNombre = User::find($event->investigadorId)?->name ?? 'Investigador';
        $email = $this->investigadorEmail->obtenerEmail($event->investigadorId);

        $mailable = $event instanceof ProrrogaAprobada
            ? new ResultadoProrrogaMailable(
                numeroPrestamo: $numeroPrestamo,
                investigadorNombre: $investigadorNombre,
                resultado: 'aprobada',
                nuevaFechaFin: $event->nuevaFechaFin->format('Y-m-d'),
            )
            : new ResultadoProrrogaMailable(
                numeroPrestamo: $numeroPrestamo,
                investigadorNombre: $investigadorNombre,
                resultado: 'rechazada',
                comentario: $event->comentario,
            );

        Mail::to($email)->send($mailable);
    }
}
