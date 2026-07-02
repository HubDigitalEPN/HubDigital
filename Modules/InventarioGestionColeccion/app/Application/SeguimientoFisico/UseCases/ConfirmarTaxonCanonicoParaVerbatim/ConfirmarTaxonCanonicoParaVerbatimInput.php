<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim;

final readonly class ConfirmarTaxonCanonicoParaVerbatimInput
{
    /**
     * @param  string[]|null  $especimenIds  Si se provee (no vacío), el enlace se
     *                                       aplica solo a esos especímenes; si es null, a todo el grupo verbatim.
     */
    public function __construct(
        public string $verbatim,
        public string $taxonId,
        public ?array $especimenIds = null,
    ) {}
}
