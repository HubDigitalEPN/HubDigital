<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo;

/**
 * Input DTO para el caso de uso de enviar una solicitud de préstamo.
 */
final readonly class EnviarSolicitudPrestamoInput
{
    /**
     * @param string $solicitudId ID de la solicitud de préstamo a enviar.
     * @param string $investigadorId ID del investigador que realiza el envío.
     */
    public function __construct(
        public string $solicitudId,
        public string $investigadorId,
    ) {}
}
