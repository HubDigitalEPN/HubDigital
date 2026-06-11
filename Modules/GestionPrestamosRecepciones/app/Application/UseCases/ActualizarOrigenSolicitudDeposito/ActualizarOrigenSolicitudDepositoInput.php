<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarOrigenSolicitudDeposito;

/**
 * Input DTO para actualizar el origen de una solicitud de depósito.
 */
final readonly class ActualizarOrigenSolicitudDepositoInput
{
    /**
     * Crea una nueva instancia de ActualizarOrigenSolicitudDepositoInput.
     * @param string $solicitudId ID de la solicitud de depósito.
     * @param string $origenRecoleccion Descripción del origen de recolección.
     * @param string $situacionRegulatoria Descripción de la situación regulatoria.
     * @param string|null $provinciaOrigen Provincia de origen (opcional).
     */
    public function __construct(
        public string $solicitudId,
        public string $origenRecoleccion,
        public string $situacionRegulatoria,
        public ?string $provinciaOrigen = null,
    ) {}
}
