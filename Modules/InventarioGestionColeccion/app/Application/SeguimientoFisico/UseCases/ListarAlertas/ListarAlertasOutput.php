<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas;

final readonly class ListarAlertasOutput
{
    /** @param ListarAlertasItemOutput[] $items */
    public function __construct(public array $items) {}
}
