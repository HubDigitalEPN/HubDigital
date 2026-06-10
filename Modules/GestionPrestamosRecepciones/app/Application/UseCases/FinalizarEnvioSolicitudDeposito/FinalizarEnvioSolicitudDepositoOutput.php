<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FinalizarEnvioSolicitudDeposito;

/**
 * Output DTO para el caso de uso de finalizar envío de solicitud.
 */
final readonly class FinalizarEnvioSolicitudDepositoOutput
{
    /**
     * @param bool $enviada Indica si la solicitud fue enviada exitosamente.
     * @param bool $alertasDerivadasACuraduria Indica si hay alertas que requieren atención de curaduría.
     * @param string $solicitudId ID de la solicitud finalizada.
     */
    public function __construct(
        public bool $enviada,
        public bool $alertasDerivadasACuraduria,
        public string $solicitudId,
    ) {}
}
