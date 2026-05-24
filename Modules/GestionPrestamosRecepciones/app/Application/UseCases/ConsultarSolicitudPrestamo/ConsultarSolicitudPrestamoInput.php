<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudPrestamo;

final readonly class ConsultarSolicitudPrestamoInput
{
    public function __construct(
        public string $solicitudId,
        public string $usuarioId,
    ) {}
}
