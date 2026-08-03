<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes\BuscarEspecimenesInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

/**
 * Siembra la jerarquía Animalia → … → Morpho peleides y un espécimen clasificado
 * en la especie. Devuelve [handler, taxonRepo, especimenRepo, especieId].
 */
function sembrarBusqueda(): array
{
    $taxonRepo = new InMemoryTaxonRepository;
    $especimenRepo = new InMemoryEspecimenRepository;

    $reino = Taxon::crear(TaxonId::generar(), 'Animalia', 'reino');
    $filo = Taxon::crear(TaxonId::generar(), 'Arthropoda', 'phylum', padreId: (string) $reino->id());
    $clase = Taxon::crear(TaxonId::generar(), 'Insecta', 'clase', padreId: (string) $filo->id());
    $orden = Taxon::crear(TaxonId::generar(), 'Lepidoptera', 'orden', padreId: (string) $clase->id());
    $familia = Taxon::crear(TaxonId::generar(), 'Nymphalidae', 'familia', padreId: (string) $orden->id());
    $genero = Taxon::crear(TaxonId::generar(), 'Morpho', 'genero', padreId: (string) $familia->id());
    $especie = Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', padreId: (string) $genero->id());
    foreach ([$reino, $filo, $clase, $orden, $familia, $genero, $especie] as $t) {
        $taxonRepo->guardar($t);
    }

    $especimenRepo->guardar(Especimen::crear(
        EspecimenId::generar(), 'MEPN-1', (string) $especie->id(), 'Yasuní', '2001-02-14', 'Juan',
    ));

    return [new BuscarEspecimenesHandler($especimenRepo, $taxonRepo), $taxonRepo, $especimenRepo];
}

test('el filtro Taxón trae el espécimen buscando por un rango alto (reino)', function (): void {
    [$handler] = sembrarBusqueda();

    $out = $handler->handle(new BuscarEspecimenesInput(taxonNombre: 'Animalia', page: 1, perPage: 25));

    expect($out->total)->toBe(1)
        ->and($out->items[0]['codigoCatalogo'])->toBe('MEPN-1');
});

test('el filtro Taxón funciona por orden y por familia', function (): void {
    [$handler] = sembrarBusqueda();

    expect($handler->handle(new BuscarEspecimenesInput(taxonNombre: 'Lepidoptera', page: 1, perPage: 25))->total)->toBe(1)
        ->and($handler->handle(new BuscarEspecimenesInput(taxonNombre: 'Nymphalidae', page: 1, perPage: 25))->total)->toBe(1);
});

test('el filtro Taxón sigue funcionando a nivel especie', function (): void {
    [$handler] = sembrarBusqueda();

    expect($handler->handle(new BuscarEspecimenesInput(taxonNombre: 'Morpho peleides', page: 1, perPage: 25))->total)->toBe(1);
});

test('un taxón inexistente devuelve vacío', function (): void {
    [$handler] = sembrarBusqueda();

    expect($handler->handle(new BuscarEspecimenesInput(taxonNombre: 'Inexistente', page: 1, perPage: 25))->total)->toBe(0);
});

test('encuentra especímenes por taxonomía verbatim aunque no estén enlazados a un Taxón', function (): void {
    // Caso típico tras importar: el espécimen trae el nombre científico en
    // taxon_verbatim pero NO quedó enlazado a una entidad Taxón (taxon_id null).
    $taxonRepo = new InMemoryTaxonRepository; // sin taxones
    $especimenRepo = new InMemoryEspecimenRepository;
    $especimenRepo->guardar(Especimen::crear(
        EspecimenId::generar(), 'MEPN-V', null, 'Yasuní', '2001-01-01', 'Juan',
        taxonVerbatim: 'Morpho peleides',
    ));

    $handler = new BuscarEspecimenesHandler($especimenRepo, $taxonRepo);

    expect($handler->handle(new BuscarEspecimenesInput(taxonNombre: 'Morpho', page: 1, perPage: 25))->total)->toBe(1)
        ->and($handler->handle(new BuscarEspecimenesInput(taxonNombre: 'Otro', page: 1, perPage: 25))->total)->toBe(0);
});

test('el filtro Familia solo acepta familias: un reino en Familia devuelve vacío', function (): void {
    [$handler] = sembrarBusqueda();

    // Documenta el caso que confundía: "Animalia" (reino) en el campo Familia
    // no matchea ninguna familia, así que devuelve vacío. En cambio una familia sí.
    expect($handler->handle(new BuscarEspecimenesInput(familia: 'Animalia', page: 1, perPage: 25))->total)->toBe(0)
        ->and($handler->handle(new BuscarEspecimenesInput(familia: 'Nymphalidae', page: 1, perPage: 25))->total)->toBe(1);
});

// ── Hoja de inventario: sin filtros, búsqueda rápida y ordenación ────────────

test('sin filtros no devuelve nada salvo que se pida explícitamente el catálogo completo', function (): void {
    [$handler] = sembrarBusqueda();

    // Comportamiento por defecto (el resto de pantallas): no traerse miles de filas.
    expect($handler->handle(new BuscarEspecimenesInput(page: 1, perPage: 25))->total)->toBe(0);

    // La hoja de inventario opta por listar todo el catálogo paginado.
    expect($handler->handle(new BuscarEspecimenesInput(page: 1, perPage: 25, permitirSinFiltros: true))->total)->toBe(1);
});

test('la búsqueda rápida matchea código, colector y taxonomía verbatim', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $especimenRepo->guardar(Especimen::crear(
        EspecimenId::generar(), 'MEPN-1', null, 'Yasuní', '2001-01-01', 'Villamarín',
        taxonVerbatim: 'Morpho peleides',
    ));
    $especimenRepo->guardar(Especimen::crear(
        EspecimenId::generar(), 'OTRO-9', null, 'Cuyabeno', '2001-01-01', 'Pérez',
    ));

    $handler = new BuscarEspecimenesHandler($especimenRepo, new InMemoryTaxonRepository);
    $buscar = fn (string $q) => $handler->handle(new BuscarEspecimenesInput(busquedaGlobal: $q, page: 1, perPage: 25));

    expect($buscar('MEPN')->total)->toBe(1)          // por código de catálogo
        ->and($buscar('villam')->total)->toBe(1)     // por colector, sin distinguir mayúsculas
        ->and($buscar('Morpho')->total)->toBe(1)     // por taxonomía verbatim
        ->and($buscar('Cuyabeno')->total)->toBe(1)   // por localidad
        ->and($buscar('nada-de-esto')->total)->toBe(0);
});

test('la búsqueda rápida también alcanza especímenes enlazados a un Taxón', function (): void {
    // El espécimen tiene taxon_id pero taxon_verbatim vacío: solo se encuentra si
    // la búsqueda resuelve el nombre contra el repositorio de taxones.
    [$handler] = sembrarBusqueda();

    expect($handler->handle(new BuscarEspecimenesInput(busquedaGlobal: 'Morpho', page: 1, perPage: 25))->total)->toBe(1);
});

test('la ordenación respeta la dirección y deja los nulos al final', function (): void {
    $repo = new InMemoryEspecimenRepository;
    // Se insertan desordenados a propósito.
    $repo->guardar(Especimen::crear(EspecimenId::generar(), 'B', null, 'X', '2005-01-01', 'c'));
    $repo->guardar(Especimen::crear(EspecimenId::generar(), 'A', null, 'X', '2001-01-01', 'a'));
    $repo->guardar(Especimen::crear(EspecimenId::generar(), 'C', null, 'X', '2010-01-01', 'b'));

    $handler = new BuscarEspecimenesHandler($repo, new InMemoryTaxonRepository);
    $codigos = fn (string $col, string $dir) => array_column(
        $handler->handle(new BuscarEspecimenesInput(
            page: 1, perPage: 25, permitirSinFiltros: true, ordenarPor: $col, ordenDireccion: $dir,
        ))->items,
        'codigoCatalogo',
    );

    expect($codigos('fechaColecta', 'asc'))->toBe(['A', 'B', 'C'])
        ->and($codigos('fechaColecta', 'desc'))->toBe(['C', 'B', 'A'])
        ->and($codigos('colector', 'asc'))->toBe(['A', 'C', 'B']);

    // `typeStatus` es null en los tres: se cae al desempate por código.
    expect($codigos('typeStatus', 'desc'))->toBe(['A', 'B', 'C']);
});

test('una clave de orden fuera de la lista blanca se ignora en vez de llegar al ORDER BY', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $repo->guardar(Especimen::crear(EspecimenId::generar(), 'B', null, 'X', '2001-01-01', 'a'));
    $repo->guardar(Especimen::crear(EspecimenId::generar(), 'A', null, 'X', '2002-01-01', 'b'));

    $handler = new BuscarEspecimenesHandler($repo, new InMemoryTaxonRepository);
    $out = $handler->handle(new BuscarEspecimenesInput(
        page: 1, perPage: 25, permitirSinFiltros: true,
        ordenarPor: 'codigo_catalogo; drop table especimenes', ordenDireccion: 'desc',
    ));

    // Cae al orden por defecto (código de catálogo ascendente).
    expect(array_column($out->items, 'codigoCatalogo'))->toBe(['A', 'B']);
});
