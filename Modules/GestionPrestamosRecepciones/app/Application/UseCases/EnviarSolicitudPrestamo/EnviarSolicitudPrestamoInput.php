<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo;

final readonly class EnviarSolicitudPrestamoInput
{
    public function __construct(
        public string $solicitudId,
        public string $investigadorId,
    ) {}
}
