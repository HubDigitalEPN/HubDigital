<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEdicionesMasivas;

final readonly class ListarEdicionesMasivasInput
{
    public function __construct(
        public int $limite = 20,
    ) {}
}
