<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico;

use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;
use Modules\CatalogoPublico\Domain\Services\ArbolTaxonomicoBuilder;

final class ConstruirArbolTaxonomicoHandler
{
    public function __construct(
        private readonly ProveedorEspecimenesParaArbolPort $proveedor,
        private readonly ArbolTaxonomicoBuilder $builder,
    ) {}

    public function __invoke(ConstruirArbolTaxonomicoInput $input): ConstruirArbolTaxonomicoOutput
    {
        $especimenes = $this->proveedor->obtenerTodos();

        $arbol = $this->builder->construir($especimenes);

        return ConstruirArbolTaxonomicoOutput::fromArbol($arbol);
    }
}
