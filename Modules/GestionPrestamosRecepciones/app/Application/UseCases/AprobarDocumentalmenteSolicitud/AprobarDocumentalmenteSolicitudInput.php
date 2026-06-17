<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud;

/**
 * Input DTO para aprobar documentalmente una solicitud que no presenta alertas pendientes.
 */
final readonly class AprobarDocumentalmenteSolicitudInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
    ) {}
}
