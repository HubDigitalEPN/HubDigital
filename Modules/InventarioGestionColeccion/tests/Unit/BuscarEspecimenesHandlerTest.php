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

test('el filtro Familia solo acepta familias: un reino en Familia devuelve vacío', function (): void {
    [$handler] = sembrarBusqueda();

    // Documenta el caso que confundía: "Animalia" (reino) en el campo Familia
    // no matchea ninguna familia, así que devuelve vacío. En cambio una familia sí.
    expect($handler->handle(new BuscarEspecimenesInput(familia: 'Animalia', page: 1, perPage: 25))->total)->toBe(0)
        ->and($handler->handle(new BuscarEspecimenesInput(familia: 'Nymphalidae', page: 1, perPage: 25))->total)->toBe(1);
});
