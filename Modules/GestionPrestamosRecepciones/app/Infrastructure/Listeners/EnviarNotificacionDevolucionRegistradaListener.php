<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\DevolucionRegistrada;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails\DevolucionRegistradaMailable;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

final class EnviarNotificacionDevolucionRegistradaListener
{
    public function __construct(
        private readonly InvestigadorEmailPort $investigadorEmail,
    ) {}

    public function handle(DevolucionRegistrada $event): void
    {
        $prestamoId = (string) $event->prestamoId;

        $prestamo       = PrestamoEloquentModel::with('acta')->find($prestamoId);
        $numeroPrestamo = $prestamo?->codigo ?? $prestamo?->acta?->codigo ?? $prestamoId;

        $investigador       = User::find($event->investigadorId);
        $investigadorNombre = $investigador?->name ?? 'Investigador';

        $email = $this->investigadorEmail->obtenerEmail($event->investigadorId);

        Mail::to($email)->send(new DevolucionRegistradaMailable(
            numeroPrestamo: $numeroPrestamo,
            investigadorNombre: $investigadorNombre,
            prestamoUrl: route('prestamos.investigador.prestamo.detalle', $prestamoId),
        ));
    }
}
