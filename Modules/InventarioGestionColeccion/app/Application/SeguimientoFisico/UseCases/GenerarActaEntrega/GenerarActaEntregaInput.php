<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\GenerarActaEntrega;

final readonly class GenerarActaEntregaInput
{
    public function __construct(
        public string $entidadDepositanteId,
    ) {}
}
