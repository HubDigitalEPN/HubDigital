<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarContenidoUnitTray;

/**
 * DTO de entrada con el unit tray cuyos especímenes se quieren consultar.
 */
final readonly class ConsultarContenidoUnitTrayInput
{
    public function __construct(
        public string $unitTrayId,
    ) {}
}
