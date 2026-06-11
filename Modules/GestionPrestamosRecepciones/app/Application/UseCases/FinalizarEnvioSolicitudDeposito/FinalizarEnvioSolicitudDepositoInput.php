<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FinalizarEnvioSolicitudDeposito;

/**
 * Input DTO para el caso de uso de finalizar el envío de una solicitud de depósito.
 */
final readonly class FinalizarEnvioSolicitudDepositoInput
{
    /**
     * @param string $solicitudId ID de la solicitud de depósito.
     * @param string $investigadorId ID del investigador.
     */
    public function __construct(
        public string $solicitudId,
        public string $investigadorId,
    ) {}
}
