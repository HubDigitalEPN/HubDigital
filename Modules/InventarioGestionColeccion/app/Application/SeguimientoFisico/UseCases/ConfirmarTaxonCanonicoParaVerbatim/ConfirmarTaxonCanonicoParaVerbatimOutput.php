<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim;

final readonly class ConfirmarTaxonCanonicoParaVerbatimOutput
{
    public function __construct(
        public string $verbatim,
        public string $taxonId,
        public int $especimenesEnlazados,
    ) {}
}
