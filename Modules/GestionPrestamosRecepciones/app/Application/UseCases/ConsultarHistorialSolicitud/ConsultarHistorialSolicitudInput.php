<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud;

/**
 * Input DTO para el caso de uso de consultar el historial de una solicitud de préstamo.
 */
final readonly class ConsultarHistorialSolicitudInput
{
    /**
     * @param string $solicitudId ID de la solicitud de préstamo.
     * @param string $usuarioId ID del usuario que consulta el historial.
     */
    public function __construct(
        public string $solicitudId,
        public string $usuarioId,
    ) {}
}
