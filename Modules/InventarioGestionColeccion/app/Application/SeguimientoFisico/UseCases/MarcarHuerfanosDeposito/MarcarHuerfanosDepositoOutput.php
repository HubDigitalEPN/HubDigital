<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarHuerfanosDeposito;

final readonly class MarcarHuerfanosDepositoOutput
{
    public function __construct(
        public int $marcados,
    ) {}
}
