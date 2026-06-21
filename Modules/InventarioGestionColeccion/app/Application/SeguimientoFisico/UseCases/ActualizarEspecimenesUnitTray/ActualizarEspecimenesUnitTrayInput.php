<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarEspecimenesUnitTray;

/**
 * DTO de entrada con el unit tray y la lista completa de especímenes que debe contener.
 */
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
