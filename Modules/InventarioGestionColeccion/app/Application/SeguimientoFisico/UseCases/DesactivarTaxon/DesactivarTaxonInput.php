<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\DesactivarTaxon;

final readonly class DesactivarTaxonInput
{
    public function __construct(public string $taxonId) {}
}
