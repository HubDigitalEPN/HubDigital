<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarLocalidadesPorNombre;

final readonly class BuscarLocalidadesPorNombreOutput
{
    /**
     * @param  array<int, array{id: string, nombreCanonico: string, rango: string}>  $items
     */
    public function __construct(
        public array $items,
    ) {}
}
