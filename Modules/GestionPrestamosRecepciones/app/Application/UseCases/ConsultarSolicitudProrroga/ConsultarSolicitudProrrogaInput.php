<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarSolicitudProrroga;

/**
 * Input DTO para consultar la solicitud de prórroga pendiente de un préstamo.
 */
final readonly class ConsultarSolicitudProrrogaInput
{
    public function __construct(
        public string $prestamoId,
    ) {}
}
