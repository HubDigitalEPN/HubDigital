<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables;

final readonly class ListarEspecimenesAsignablesOutput
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(
        public array $items,
    ) {}
}
