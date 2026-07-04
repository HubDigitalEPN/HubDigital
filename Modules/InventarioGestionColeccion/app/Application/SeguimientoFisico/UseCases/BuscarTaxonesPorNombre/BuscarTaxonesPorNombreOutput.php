<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarTaxonesPorNombre;

final readonly class BuscarTaxonesPorNombreOutput
{
    /**
     * @param  array<int, array{id: string, nombreCientifico: string, rango: string}>  $items
     */
    public function __construct(
        public array $items,
    ) {}
}
