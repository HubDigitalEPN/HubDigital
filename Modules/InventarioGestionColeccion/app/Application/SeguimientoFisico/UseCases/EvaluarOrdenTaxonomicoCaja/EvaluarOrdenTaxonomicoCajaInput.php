<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EvaluarOrdenTaxonomicoCaja;

final readonly class EvaluarOrdenTaxonomicoCajaInput
{
    public function __construct(
        public string $cajaId,
        public string $ranuraId,
    ) {}
}
