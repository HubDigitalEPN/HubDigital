<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarLocalidadesPorVerbatim;

final readonly class BuscarLocalidadesPorVerbatimInput
{
    public function __construct(
        public string $verbatim,
        public int $limite = 20,
    ) {}
}
