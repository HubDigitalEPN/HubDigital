<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarOcupacionGabinete;

final readonly class ConsultarOcupacionGabineteOutput
{
    /** @param ConsultarOcupacionGabineteItemOutput[] $items */
    public function __construct(
        public array $items,
    ) {}
}
