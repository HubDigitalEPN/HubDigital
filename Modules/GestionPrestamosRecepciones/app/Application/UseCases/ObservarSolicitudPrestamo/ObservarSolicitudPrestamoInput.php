<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ObservarSolicitudPrestamo;

final readonly class ObservarSolicitudPrestamoInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public string $observacion,
    ) {}
}
