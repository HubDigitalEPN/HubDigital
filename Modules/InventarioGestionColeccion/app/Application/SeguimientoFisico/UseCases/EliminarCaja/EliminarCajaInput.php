<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarCaja;

/**
 * DTO de entrada con la caja que se quiere eliminar.
 */
final readonly class EliminarCajaInput
{
    public function __construct(
        public string $cajaId,
    ) {}
}
