<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarSolicitudPrestamo;

/**
 * Datos de entrada para rechazar una solicitud de préstamo.
 */
final readonly class RechazarSolicitudPrestamoInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public string $motivo,
    ) {}
}
