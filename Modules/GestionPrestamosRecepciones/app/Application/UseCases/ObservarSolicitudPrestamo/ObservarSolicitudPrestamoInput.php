<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo;

/**
 * Datos de entrada para observar una solicitud de préstamo.
 */
final readonly class ObservarSolicitudPrestamoInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public string $observacion,
    ) {}
}
