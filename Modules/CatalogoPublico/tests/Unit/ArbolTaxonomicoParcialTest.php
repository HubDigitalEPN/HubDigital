<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Domain\Services\ArbolTaxonomicoBuilder;
use Modules\CatalogoPublico\Domain\ValueObjects\EspecimenParaArbol;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

test('el catálogo conserva filos y registros sin identificación de especie', function (): void {
    $specimens = [
        EspecimenParaArbol::crear('EPN-1', JerarquiaTaxonomica::parcial(phylum: 'Arthropoda'), true, true, 'uno'),
        EspecimenParaArbol::crear('EPN-2', JerarquiaTaxonomica::parcial(phylum: 'Mollusca', class: 'Gastropoda'), true, true, 'dos'),
        EspecimenParaArbol::crear('EPN-3', JerarquiaTaxonomica::parcial(), true, true, 'tres'),
    ];

    $arbol = (new ArbolTaxonomicoBuilder)->construir($specimens);

    expect(array_map(fn ($n): string => $n->taxon, $arbol->nodosDeRango(RangoTaxonomico::Phylum)))
        ->toBe(['Arthropoda', 'Mollusca'])
        ->and($arbol->especimenIds)->toBe(['uno', 'dos', 'tres'])
        ->and($arbol->especimenesPorNodo['phylum:Mollusca'])->toBe(['dos'])
        ->and($arbol->especimenesSinFilo)->toBe(['tres']);
});
