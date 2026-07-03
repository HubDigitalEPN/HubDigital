<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Listeners;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Modules\GestionPrestamosRecepciones\Application\Ports\InvestigadorEmailPort;
use Modules\GestionPrestamosRecepciones\Domain\Events\PrestamoCerrado;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\Mails\CierrePrestamoMailable;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models\PrestamoEloquentModel;

final class EnviarNotificacionCierrePrestamoListener
{
    public function __construct(
        private readonly InvestigadorEmailPort $investigadorEmail,
    ) {}

    public function handle(PrestamoCerrado $event): void
    {
        $prestamoId = (string) $event->prestamoId;

        $prestamo    = PrestamoEloquentModel::with('acta')->find($prestamoId);
        $numeroPrestamo = $prestamo?->codigo ?? $prestamo?->acta?->codigo ?? $prestamoId;

        $investigador = User::find($event->investigadorId);
        $investigadorNombre = $investigador?->name ?? 'Investigador';

        $email = $this->investigadorEmail->obtenerEmail($event->investigadorId);

        $resultadoLabel = match ($event->resultado->value) {
            'sin_novedades' => 'Sin novedades',
            'con_observacion' => 'Con observación',
            default => $event->resultado->value,
        };

        $condicionLabel = match ($event->condicionEspecimen->value) {
            'apto'    => 'Apto',
            'no_apto' => 'No apto',
            default   => $event->condicionEspecimen->value,
        };

        Mail::to($email)->send(new CierrePrestamoMailable(
            numeroPrestamo: $numeroPrestamo,
            investigadorNombre: $investigadorNombre,
            resultado: $resultadoLabel,
            condicion: $condicionLabel,
            esConObservacion: $event->condicionEspecimen->value !== 'apto',
            observacion: $event->observacion,
            prestamoUrl: route('prestamos.investigador.prestamo.detalle', $prestamoId),
        ));
    }
}
