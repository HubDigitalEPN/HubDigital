<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarRanurasGabinete;

final readonly class ListarRanurasGabineteInput
{
    public function __construct(public string $gabineteId) {}
}
