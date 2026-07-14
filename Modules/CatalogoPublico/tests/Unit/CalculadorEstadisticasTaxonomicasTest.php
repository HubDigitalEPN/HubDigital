<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Services\CalculadorEstadisticasTaxonomicas;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;
use Modules\CatalogoPublico\Tests\Support\InMemoryEspecimenDivulgableRepository;
use Modules\CatalogoPublico\Tests\Support\InMemoryProveedorEspecimenesParaArbol;

function sembrarTaxon(
    InMemoryProveedorEspecimenesParaArbol $arbol,
    InMemoryEspecimenDivulgableRepository $repo,
    string $occurrenceID,
    string $family,
    string $genus,
    string $species,
    bool $divulgar = true,
    ?ConfiguracionVisibilidad $config = null,
): void {
    $especimenId = (string) Str::uuid();
    $repo->registrarOccurrence($occurrenceID, $especimenId);

    if ($divulgar) {
        $repo->guardar(EspecimenDivulgable::sincronizar(
            id: $repo->nextIdentity(),
            especimenId: $especimenId,
            configuracion: $config ?? ConfiguracionVisibilidad::todosHabilitados(),
        ));
    }

    $arbol->agregar(
        occurrenceID: $occurrenceID,
        jerarquia: JerarquiaTaxonomica::desde(
            phylum: 'Arthropoda',
            class: 'Insecta',
            order: 'Hymenoptera',
            family: $family,
            genus: $genus,
            specificEpithet: explode(' ', $species)[1] ?? 'sp',
            scientificName: $species,
        ),
    );
}

function nuevoCalculador(): array
{
    $repo = new InMemoryEspecimenDivulgableRepository;
    $arbol = new InMemoryProveedorEspecimenesParaArbol($repo);
    $calc = new CalculadorEstadisticasTaxonomicas($arbol);

    return [$calc, $repo, $arbol];
}

test('topPorRango devuelve familias ordenadas por cantidad descendente', function (): void {
    [$calc, $repo, $arbol] = nuevoCalculador();
    sembrarTaxon($arbol, $repo, 'EPN-1', 'Formicidae', 'Atta', 'Atta cephalotes');
    sembrarTaxon($arbol, $repo, 'EPN-2', 'Formicidae', 'Atta', 'Atta laevigata');
    sembrarTaxon($arbol, $repo, 'EPN-3', 'Formicidae', 'Solenopsis', 'Solenopsis invicta');
    sembrarTaxon($arbol, $repo, 'EPN-4', 'Apidae', 'Bombus', 'Bombus atratus');
    sembrarTaxon($arbol, $repo, 'EPN-5', 'Nymphalidae', 'Morpho', 'Morpho menelaus');

    $top = $calc->topPorRango(RangoTaxonomico::Family, 3);

    expect($top)->toHaveCount(3)
        ->and($top[0]->nombre)->toBe('Formicidae')
        ->and($top[0]->cantidadRegistros)->toBe(3)
        ->and($top[1]->cantidadRegistros)->toBe(1)
        ->and($top[2]->cantidadRegistros)->toBe(1);
});

test('conteoTotalTaxonesUnicos cuenta taxones distintos, no registros', function (): void {
    [$calc, $repo, $arbol] = nuevoCalculador();
    sembrarTaxon($arbol, $repo, 'EPN-1', 'Formicidae', 'Atta', 'Atta cephalotes');
    sembrarTaxon($arbol, $repo, 'EPN-2', 'Formicidae', 'Atta', 'Atta laevigata');
    sembrarTaxon($arbol, $repo, 'EPN-3', 'Formicidae', 'Solenopsis', 'Solenopsis invicta');

    expect($calc->conteoTotalTaxonesUnicos(RangoTaxonomico::Family))->toBe(1)
        ->and($calc->conteoTotalTaxonesUnicos(RangoTaxonomico::Genus))->toBe(2)
        ->and($calc->conteoTotalTaxonesUnicos(RangoTaxonomico::Species))->toBe(3);
});

test('ignora especímenes no divulgados en el árbol', function (): void {
    [$calc, $repo, $arbol] = nuevoCalculador();
    sembrarTaxon($arbol, $repo, 'EPN-1', 'Formicidae', 'Atta', 'Atta cephalotes');
    sembrarTaxon($arbol, $repo, 'EPN-2', 'Apidae', 'Bombus', 'Bombus atratus', divulgar: false);

    expect($calc->conteoTotalTaxonesUnicos(RangoTaxonomico::Family))->toBe(1);
    expect($calc->topPorRango(RangoTaxonomico::Family, 5)[0]->nombre)->toBe('Formicidae');
});

test('ignora especímenes cuyo género o nombre científico no son visibles en el árbol', function (): void {
    [$calc, $repo, $arbol] = nuevoCalculador();

    $flags = ConfiguracionVisibilidad::todosHabilitados()->toArray();
    $flags['genusVisible'] = false;
    $configOculta = ConfiguracionVisibilidad::desde($flags);

    sembrarTaxon($arbol, $repo, 'EPN-1', 'Formicidae', 'Atta', 'Atta cephalotes');
    sembrarTaxon($arbol, $repo, 'EPN-2', 'Apidae', 'Bombus', 'Bombus atratus', config: $configOculta);

    $top = $calc->topPorRango(RangoTaxonomico::Family, 5);

    expect($top)->toHaveCount(1)
        ->and($top[0]->nombre)->toBe('Formicidae');
});

test('limite 0 o negativo devuelve lista vacía', function (): void {
    [$calc, $repo, $arbol] = nuevoCalculador();
    sembrarTaxon($arbol, $repo, 'EPN-1', 'Formicidae', 'Atta', 'Atta cephalotes');

    expect($calc->topPorRango(RangoTaxonomico::Family, 0))->toBe([])
        ->and($calc->topPorRango(RangoTaxonomico::Family, -3))->toBe([]);
});
