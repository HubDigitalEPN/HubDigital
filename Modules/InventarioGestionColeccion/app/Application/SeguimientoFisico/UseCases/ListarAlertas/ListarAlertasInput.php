<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas;

final readonly class ListarAlertasInput
{
    public function __construct(public ?string $estado = null) {}
}
