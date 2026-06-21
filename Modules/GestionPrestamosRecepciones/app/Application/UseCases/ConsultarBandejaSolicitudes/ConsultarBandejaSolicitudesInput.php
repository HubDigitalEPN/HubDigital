<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarBandejaSolicitudes;

/**
 * Datos de entrada para consultar la bandeja de solicitudes del curador.
 *
 * {@see ConsultarBandejaSolicitudesHandler}
 */
final readonly class ConsultarBandejaSolicitudesInput
{
    public function __construct(
        public string $busqueda = '',
        public string $estado = '',
        public string $busquedaInvestigador = '',
        public string $ordenCampo = 'fecha',
        public string $ordenDireccion = 'desc',
    ) {}
}
