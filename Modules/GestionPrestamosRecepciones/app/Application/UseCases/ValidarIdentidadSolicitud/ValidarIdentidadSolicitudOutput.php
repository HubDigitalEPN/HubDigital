<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionIdentidad;

/**
 * Datos de salida tras validar la identidad en la solicitud.
 */
final readonly class ValidarIdentidadSolicitudOutput
{
    public function __construct(
        public ResultadoValidacionIdentidad $resultado,
        /** @var string[] */
        public array $accionesPermitidas,
    ) {}
}
