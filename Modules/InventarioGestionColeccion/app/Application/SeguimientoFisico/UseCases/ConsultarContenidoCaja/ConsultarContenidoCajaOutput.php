<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoCaja;

/**
 * DTO de salida con la lista de unit trays de la caja consultada.
 */
final readonly class ConsultarContenidoCajaOutput
{
    /** @param ConsultarContenidoCajaItemOutput[] $items */
    public function __construct(
        public array $items,
    ) {}
}
