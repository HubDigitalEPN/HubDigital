<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\RechazarDocumentalmenteSolicitud;

/**
 * Input DTO para que el curador rechace documentalmente una solicitud, indicando el
 * tipo de rechazo (Subsanable o Definitivo) y el motivo.
 */
final readonly class RechazarDocumentalmenteSolicitudInput
{
    public function __construct(
        public string $solicitudId,
        public string $curadorId,
        public string $tipoRechazo,
        public string $motivo,
    ) {}
}
