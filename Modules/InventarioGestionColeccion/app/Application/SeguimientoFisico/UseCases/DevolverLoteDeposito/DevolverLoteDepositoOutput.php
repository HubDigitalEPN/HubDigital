<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DevolverLoteDeposito;

/** Cuántos especímenes salieron efectivamente de la colección. */
final readonly class DevolverLoteDepositoOutput
{
    public function __construct(
        public int $especimenesDevueltos,
    ) {}
}
