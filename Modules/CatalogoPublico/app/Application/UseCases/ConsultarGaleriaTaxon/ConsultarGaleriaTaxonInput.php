<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon;

final readonly class ConsultarGaleriaTaxonInput
{
    public function __construct(
        public string $nivel,
        public string $valorTaxon,
    ) {}
}
