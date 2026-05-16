<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito;

final readonly class EnviarSolicitudDepositoInput
{
    public function __construct(
        public string $solicitudId,
    ) {}
}
