<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico;

use Modules\CatalogoPublico\Domain\ValueObjects\ArbolTaxonomico;
use Modules\CatalogoPublico\Domain\ValueObjects\NodoEspecie;
use Modules\CatalogoPublico\Domain\ValueObjects\NodoTaxonomico;

final readonly class ConstruirArbolTaxonomicoOutput
{
    /**
     * @param  list<array{nivel: string, taxon: string, padre: string}>  $nodosJerarquicos
     * @param  list<array{especie: string, genus: string, specificEpithet: string, padre: string}>  $especies
     * @param  array<string, list<string>>  $especimenesPorEspecie  especie → [occurrenceIDs]
     */
    private function __construct(
        public array $nodosJerarquicos,
        public array $especies,
        public array $especimenesPorEspecie,
    ) {}

    public static function fromArbol(ArbolTaxonomico $arbol): self
    {
        $nodosJerarquicos = array_map(
            fn (NodoTaxonomico $n) => [
                'nivel' => $n->rango->value,
                'taxon' => $n->taxon,
                'padre' => $n->nombrePadre,
            ],
            $arbol->nodosJerarquicos
        );

        $especies = array_map(
            fn (NodoEspecie $e) => [
                'especie' => $e->especie,
                'genus' => $e->genus,
                'specificEpithet' => $e->specificEpithet,
                'padre' => $e->padreGenus,
            ],
            $arbol->especies
        );

        return new self(
            nodosJerarquicos: $nodosJerarquicos,
            especies: $especies,
            especimenesPorEspecie: $arbol->especimenesPorEspecie,
        );
    }
}
