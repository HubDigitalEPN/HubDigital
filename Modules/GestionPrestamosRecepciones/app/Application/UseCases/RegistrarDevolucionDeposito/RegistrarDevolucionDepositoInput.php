<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarDevolucionDeposito;

/** Input para registrar que el material de un depósito volvió a su depositante. */
final readonly class RegistrarDevolucionDepositoInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
    ) {}
}
