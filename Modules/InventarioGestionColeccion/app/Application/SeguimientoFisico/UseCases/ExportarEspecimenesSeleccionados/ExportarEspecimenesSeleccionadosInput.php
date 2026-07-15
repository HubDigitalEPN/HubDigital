<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ExportarEspecimenesSeleccionados;

final readonly class ExportarEspecimenesSeleccionadosInput
{
    /** @param string[] $ids IDs de especímenes a exportar. */
    public function __construct(
        public array $ids,
    ) {}
}
