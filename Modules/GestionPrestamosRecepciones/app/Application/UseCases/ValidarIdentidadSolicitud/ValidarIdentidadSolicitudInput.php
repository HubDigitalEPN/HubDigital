<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud;

/**
 * Datos de entrada para validar la identidad en la solicitud.
 */
final readonly class ValidarIdentidadSolicitudInput
{
    public function __construct(
        public string $solicitudId,
        public string $nombrePerfil,
        public string $nombreEnDocumento,
    ) {}
}
