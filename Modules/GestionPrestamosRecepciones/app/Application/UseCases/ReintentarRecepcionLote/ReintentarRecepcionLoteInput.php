<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ReintentarRecepcionLote;

/** Input para reabrir la verificación de un lote suspendido y reintentar su recepción. */
final readonly class ReintentarRecepcionLoteInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
    ) {}
}
