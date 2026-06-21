<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarMisSolicitudes;

/**
 * Resultado de la consulta del listado "Mis solicitudes".
 *
 * {@see ConsultarMisSolicitudesHandler}
 */
final readonly class ConsultarMisSolicitudesOutput
{
    /**
     * @param array<int, FilaMiSolicitud> $filas
     */
    public function __construct(
        public array $filas,
    ) {}
}
