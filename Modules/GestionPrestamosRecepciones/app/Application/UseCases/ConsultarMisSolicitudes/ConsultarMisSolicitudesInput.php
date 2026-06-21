<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarMisSolicitudes;

/**
 * Datos de entrada para consultar las solicitudes propias de un investigador.
 *
 * {@see ConsultarMisSolicitudesHandler}
 */
final readonly class ConsultarMisSolicitudesInput
{
    public function __construct(
        public string $investigadorId,
        public string $busqueda = '',
        public string $estado = '',
        public string $ordenCampo = 'fecha',
        public string $ordenDireccion = 'desc',
    ) {}
}
