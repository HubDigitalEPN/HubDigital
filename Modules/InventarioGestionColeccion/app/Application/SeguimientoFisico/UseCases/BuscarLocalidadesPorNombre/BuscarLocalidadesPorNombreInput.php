<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarLocalidadesPorNombre;

final readonly class BuscarLocalidadesPorNombreInput
{
    public function __construct(
        public string $consulta,
        public int $limite = 20,
    ) {}
}
