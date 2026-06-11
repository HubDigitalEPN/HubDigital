<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas;

/**
 * DTO de salida con la lista de cajas del inventario.
 */
final readonly class ListarCajasOutput
{
    /** @param ListarCajasItemOutput[] $items */
    public function __construct(public array $items) {}
}
