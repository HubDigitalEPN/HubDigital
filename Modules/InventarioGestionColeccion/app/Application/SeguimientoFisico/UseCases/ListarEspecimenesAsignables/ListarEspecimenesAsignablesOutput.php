<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesAsignables;

/**
 * DTO de salida con la lista de especímenes asignables proyectados para la UI.
 */
final readonly class ListarEspecimenesAsignablesOutput
{
    /** @param array<int, array<string, mixed>> $items */
    public function __construct(
        public array $items,
    ) {}
}
