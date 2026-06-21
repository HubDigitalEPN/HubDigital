<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarAlertas;

/**
 * DTO de salida con la lista de alertas de ubicación encontradas.
 */
final readonly class ListarAlertasOutput
{
    /** @param ListarAlertasItemOutput[] $items */
    public function __construct(public array $items) {}
}
