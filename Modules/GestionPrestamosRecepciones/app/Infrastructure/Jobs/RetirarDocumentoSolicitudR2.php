<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ColaFirmasSolicitudR2;

/** La retirada conserva sus rutas aunque se elimine después el borrador. */
final class RetirarDocumentoSolicitudR2 implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;
    public int $timeout = 300;

    /** @param list<string> $rutas Rutas de la versión retirada, leídas por el servidor. */
    public function __construct(
        public readonly string $solicitudId,
        public readonly string $nombre,
        public readonly array $rutas,
    ) {}

    public function backoff(): array { return [5, 30, 60, 300]; }

    public function handle(ColaFirmasSolicitudR2 $cola, AlmacenamientoDepositos $almacenamiento): void
    {
        $pendientes = DB::transaction(fn (): array => $cola->cancelarDocumento($this->solicitudId, $this->nombre, $this->rutas));
        foreach (array_unique([...$this->rutas, ...$pendientes]) as $ruta) {
            $almacenamiento->eliminar($ruta);
        }
    }
}
