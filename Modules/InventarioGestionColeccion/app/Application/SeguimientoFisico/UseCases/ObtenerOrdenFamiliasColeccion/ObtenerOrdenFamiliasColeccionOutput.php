<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerOrdenFamiliasColeccion;

final readonly class ObtenerOrdenFamiliasColeccionOutput
{
    /** @param FamiliaColeccionOutput[] $familias */
    public function __construct(public array $familias) {}
}
