<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray;

/**
 * DTO de salida con la lista de especímenes del unit tray consultado.
 */
final readonly class ConsultarContenidoUnitTrayOutput
{
    /** @param ConsultarContenidoUnitTrayItemOutput[] $items */
    public function __construct(
        public array $items,
    ) {}
}
