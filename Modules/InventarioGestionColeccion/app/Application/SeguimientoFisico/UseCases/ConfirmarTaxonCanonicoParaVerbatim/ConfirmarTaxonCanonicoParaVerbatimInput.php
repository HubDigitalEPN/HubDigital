<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim;

final readonly class ConfirmarTaxonCanonicoParaVerbatimInput
{
    public function __construct(
        public string $verbatim,
        public string $taxonId,
    ) {}
}
