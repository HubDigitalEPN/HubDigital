<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;

final class PersistirFirmaSolicitudR2 implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;
    public int $timeout = 300;

    public function __construct(public readonly string $pendienteId) {}

    public function backoff(): array { return [5, 30, 60, 300]; }

    public function handle(ColaFirmasSolicitudR2 $cola, AlmacenamientoDepositos $almacenamiento): void
    {
        $cola->entregar($this->pendienteId, $almacenamiento);
    }
}
