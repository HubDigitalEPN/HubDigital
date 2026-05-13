<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes;

final readonly class BuscarEspecimenesOutput
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(
        public array $items,
    ) {}
}
