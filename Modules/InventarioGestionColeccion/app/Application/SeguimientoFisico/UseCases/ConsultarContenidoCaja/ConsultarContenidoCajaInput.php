<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoCaja;

final readonly class ConsultarContenidoCajaInput
{
    public function __construct(
        public string $cajaId,
    ) {}
}
