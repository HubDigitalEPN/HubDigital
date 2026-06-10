<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito;

/**
 * Input DTO para el caso de uso de enviar una solicitud de depósito a revisión.
 */
final readonly class EnviarSolicitudDepositoInput
{
    /**
     * @param string $solicitudId ID de la solicitud de depósito.
     */
    public function __construct(
        public string $solicitudId,
    ) {}
}
