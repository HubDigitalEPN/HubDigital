<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ObtenerOrdenFamiliasColeccion;

/**
 * DTO de salida con la lista ordenada de familias de la colección.
 */
final readonly class ObtenerOrdenFamiliasColeccionOutput
{
    /** @param FamiliaColeccionOutput[] $familias */
    public function __construct(public array $familias) {}
}
