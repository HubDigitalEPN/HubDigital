<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoCaja;

final readonly class ConsultarContenidoCajaOutput
{
    /** @param ConsultarContenidoCajaItemOutput[] $items */
    public function __construct(
        public array $items,
    ) {}
}
