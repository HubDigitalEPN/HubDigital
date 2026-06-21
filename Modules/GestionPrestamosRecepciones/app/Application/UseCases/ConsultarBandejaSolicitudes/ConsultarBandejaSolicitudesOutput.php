<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaSolicitudes;

/**
 * Resultado de la consulta de la bandeja de solicitudes del curador.
 *
 * {@see ConsultarBandejaSolicitudesHandler}
 */
final readonly class ConsultarBandejaSolicitudesOutput
{
    /**
     * @param array<int, FilaSolicitudBandeja> $filas
     */
    public function __construct(
        public array $filas,
    ) {}
}
