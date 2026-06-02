<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray;

final readonly class ActualizarEspecimenesUnitTrayInput
{
    /**
     * @param  string[]  $especimenIds  Lista completa (reemplaza la anterior)
     */
    public function __construct(
        public string $unitTrayId,
        public array $especimenIds,
    ) {}
}
