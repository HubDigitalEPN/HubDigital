<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FinalizarEnvioSolicitudDeposito;

final readonly class FinalizarEnvioSolicitudDepositoOutput
{
    public function __construct(
        public bool $enviada,
        public bool $alertasDerivadasACuraduria,
        public string $solicitudId,
    ) {}
}
