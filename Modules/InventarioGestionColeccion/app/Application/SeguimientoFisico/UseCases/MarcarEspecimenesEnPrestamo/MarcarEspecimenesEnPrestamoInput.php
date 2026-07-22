<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenesEnPrestamo;

final readonly class MarcarEspecimenesEnPrestamoInput
{
    /**
     * @param  string[]  $especimenIds  UUIDs de `taxonomia.especimenes` que salen en préstamo.
     */
    public function __construct(
        public array $especimenIds,
    ) {}
}
