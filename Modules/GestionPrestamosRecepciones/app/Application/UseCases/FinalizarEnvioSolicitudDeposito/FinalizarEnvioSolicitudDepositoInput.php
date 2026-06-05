<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FinalizarEnvioSolicitudDeposito;

final readonly class FinalizarEnvioSolicitudDepositoInput
{
    public function __construct(
        public string $solicitudId,
        public string $investigadorId,
    ) {}
}
