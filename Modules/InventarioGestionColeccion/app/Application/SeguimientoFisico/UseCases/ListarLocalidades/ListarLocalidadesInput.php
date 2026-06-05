<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidades;

final readonly class ListarLocalidadesInput
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 25,
    ) {}
}
