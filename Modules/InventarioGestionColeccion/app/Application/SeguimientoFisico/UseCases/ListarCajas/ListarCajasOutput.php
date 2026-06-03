<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCajas;

final readonly class ListarCajasOutput
{
    /** @param CajaListadoItem[] $items */
    public function __construct(public array $items) {}
}
