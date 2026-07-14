<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesDeMuestra;

final readonly class ListarEspecimenesDeMuestraInput
{
    public function __construct(
        public string $muestraId,
        public int $limite = 500,
    ) {}
}
