<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarResumenLoteDeposito;

/**
 * Lo que la colección custodia de un depósito.
 *
 * `devueltos` cuenta el material que ya volvió a su depositante: sigue en la tabla
 * porque el rastro de qué estuvo bajo custodia es documentación, pero no está en el
 * museo.
 */
final readonly class ConsultarResumenLoteDepositoOutput
{
    public function __construct(
        public int $especimenesEnColeccion,
        public int $pendientesRevision,
        public int $devueltos,
    ) {}

    public function hayMaterialIngresado(): bool
    {
        return $this->especimenesEnColeccion > 0;
    }
}
