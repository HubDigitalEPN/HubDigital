<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEntidadesDepositantes;

final readonly class ListarEntidadesDepositantesOutput
{
    /** @param ListarEntidadesDepositantesItemOutput[] $items */
    public function __construct(public array $items) {}
}
