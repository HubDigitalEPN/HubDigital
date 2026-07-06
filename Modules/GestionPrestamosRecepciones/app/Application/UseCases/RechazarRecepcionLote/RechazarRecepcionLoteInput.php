<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarRecepcionLote;

/** Input para rechazar la recepción de un lote por una anomalía de integridad subsanable. */
final readonly class RechazarRecepcionLoteInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public string $motivoFallo,
    ) {}
}
