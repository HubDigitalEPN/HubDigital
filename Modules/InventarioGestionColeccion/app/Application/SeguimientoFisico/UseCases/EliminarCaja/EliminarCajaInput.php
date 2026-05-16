<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EliminarCaja;

final readonly class EliminarCajaInput
{
    public function __construct(
        public string $cajaId,
    ) {}
}
