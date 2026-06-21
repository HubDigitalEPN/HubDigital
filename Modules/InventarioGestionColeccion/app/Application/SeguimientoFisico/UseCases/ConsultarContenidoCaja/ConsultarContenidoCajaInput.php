<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoCaja;

/**
 * DTO de entrada con la caja cuyos unit trays se quieren consultar.
 */
final readonly class ConsultarContenidoCajaInput
{
    public function __construct(
        public string $cajaId,
    ) {}
}
