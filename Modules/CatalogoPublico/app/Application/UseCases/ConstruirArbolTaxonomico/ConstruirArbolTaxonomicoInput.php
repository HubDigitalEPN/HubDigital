<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico;

use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;

final readonly class ConstruirArbolTaxonomicoInput
{
    public function __construct(
        public readonly ?FiltrosBusqueda $filtros = null,
    ) {}
}
