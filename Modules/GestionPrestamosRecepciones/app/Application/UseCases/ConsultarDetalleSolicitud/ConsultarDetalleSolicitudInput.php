<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleSolicitud;

/**
 * Input DTO para consultar el detalle completo de una solicitud de préstamo.
 */
final readonly class ConsultarDetalleSolicitudInput
{
    /**
     * @param string $solicitudId Identificador de la solicitud de préstamo.
     */
    public function __construct(
        public string $solicitudId,
    ) {}
}
