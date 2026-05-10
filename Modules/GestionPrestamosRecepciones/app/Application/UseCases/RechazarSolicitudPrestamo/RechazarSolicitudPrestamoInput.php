<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarSolicitudPrestamo;

final readonly class RechazarSolicitudPrestamoInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public string $motivo,
    ) {}
}
