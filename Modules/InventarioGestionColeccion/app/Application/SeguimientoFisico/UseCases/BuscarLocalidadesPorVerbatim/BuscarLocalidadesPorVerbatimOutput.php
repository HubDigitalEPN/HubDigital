<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarLocalidadesPorVerbatim;

final readonly class BuscarLocalidadesPorVerbatimOutput
{
    /** @param BuscarLocalidadesPorVerbatimItem[] $items */
    public function __construct(
        public array $items,
        public string $verbatimConsultado,
    ) {}
}
