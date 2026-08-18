<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\SanearDatosDwCDeposito;

final readonly class SanearDatosDwCDepositoOutput
{
    public function __construct(
        public int $especimenesTocados,
        public int $columnasEscritas,
        public bool $simulado,
    ) {}
}
