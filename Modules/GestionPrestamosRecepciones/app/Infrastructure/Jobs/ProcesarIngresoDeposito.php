<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\IngresoPendienteDeposito;

final class ProcesarIngresoDeposito implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // Los tres intentos totales y su recuperación pertenecen al outbox.

    public int $timeout = 300;

    public function __construct(public string $solicitudId)
    {
        if (config('hubdigital.validation_mode')) {
            $cola = trim((string) config('hubdigital.validation_queue', ''));
            if ($cola === 'default' || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', $cola) !== 1) {
                throw new \RuntimeException('La validación requiere una cola dedicada válida distinta de default.');
            }
            $this->onQueue($cola);
        }
    }

    public function handle(IngresoPendienteDeposito $pendientes): void
    {
        $pendientes->procesar($this->solicitudId);
    }
}
