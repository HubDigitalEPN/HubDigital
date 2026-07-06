<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones;

/** Input para aceptar la recepción de un lote con observaciones por una anomalía no devolvible. */
final readonly class AceptarRecepcionConObservacionesInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public string $tipoObservacion,
        public ?string $detalleObservacion = null,
    ) {}
}
