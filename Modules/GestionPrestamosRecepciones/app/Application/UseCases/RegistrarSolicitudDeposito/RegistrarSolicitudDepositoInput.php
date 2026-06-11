<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito;

/**
 * Datos de entrada para registrar una solicitud de depósito.
 */
final readonly class RegistrarSolicitudDepositoInput
{
    public function __construct(
        public string $investigadorId,
        public string $tipoTramite,
    ) {}
}
