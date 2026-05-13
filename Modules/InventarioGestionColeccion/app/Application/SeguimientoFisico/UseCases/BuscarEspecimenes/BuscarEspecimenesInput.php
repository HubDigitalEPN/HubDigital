<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes;

final readonly class BuscarEspecimenesInput
{
    public function __construct(
        public string $criterio,
        public string $valor,
    ) {}
}
