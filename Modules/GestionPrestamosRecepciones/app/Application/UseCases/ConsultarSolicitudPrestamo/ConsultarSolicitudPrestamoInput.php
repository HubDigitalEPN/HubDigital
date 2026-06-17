<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudPrestamo;

/**
 * Input DTO para el caso de uso de consultar una solicitud de préstamo.
 */
final readonly class ConsultarSolicitudPrestamoInput
{
    /**
     * @param string $solicitudId ID de la solicitud de préstamo.
     * @param string $usuarioId ID del usuario que realiza la consulta.
     */
    public function __construct(
        public string $solicitudId,
        public string $usuarioId,
    ) {}
}
