<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarHistorialSolicitud;

final readonly class ConsultarHistorialSolicitudInput
{
    public function __construct(
        public string $solicitudId,
        public string $usuarioId,
    ) {}
}
