<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function crearTaxon(string $nombre = 'Morpho peleides', string $rango = 'especie'): Taxon
{
    return Taxon::crear(TaxonId::generar(), $nombre, $rango, 'Kollar', 1850);
}

test('guardar y recuperar un taxon por id', function (): void {
    $repo = app(TaxonRepositoryInterface::class);
    $taxon = crearTaxon();

    $repo->guardar($taxon);

    $encontrado = $repo->buscarPorId($taxon->id());

    expect($encontrado)->not->toBeNull()
        ->and($encontrado->nombreCientifico())->toBe('Morpho peleides')
        ->and($encontrado->rango()->value)->toBe('especie');
});

test('nextIdentity devuelve un TaxonId unico', function (): void {
    $repo = app(TaxonRepositoryInterface::class);

    $id1 = $repo->nextIdentity();
    $id2 = $repo->nextIdentity();

    expect((string) $id1)->not->toBe((string) $id2);
});

test('guardar actualiza un taxon existente', function (): void {
    $repo = app(TaxonRepositoryInterface::class);
    $taxon = crearTaxon();
    $repo->guardar($taxon);

    $taxon->actualizar('Morpho menelaus', 'Linnaeus', 1758);
    $repo->guardar($taxon);

    $encontrado = $repo->buscarPorId($taxon->id());

    expect($encontrado->nombreCientifico())->toBe('Morpho menelaus')
        ->and($encontrado->autor())->toBe('Linnaeus');
});

test('buscarPorNombreContiene devuelve taxones que contienen la cadena', function (): void {
    $repo = app(TaxonRepositoryInterface::class);
    $repo->guardar(crearTaxon('Morpho peleides', 'especie'));
    $repo->guardar(crearTaxon('Morpho menelaus', 'especie'));
    $repo->guardar(Taxon::crear(TaxonId::generar(), 'Heliconius melpomene', 'especie', 'Linnaeus', 1758));

    $resultado = $repo->buscarPorNombreContiene('Morpho');

    expect($resultado)->toHaveCount(2);
});

test('buscarTodos devuelve todos los taxones', function (): void {
    $repo = app(TaxonRepositoryInterface::class);
    $repo->guardar(crearTaxon('Morpho peleides', 'especie'));
    $repo->guardar(Taxon::crear(TaxonId::generar(), 'Lepidoptera', 'orden', 'Linnaeus', 1758));

    $resultado = $repo->buscarTodos();

    expect($resultado)->toHaveCount(2);
});

test('buscarPorIds devuelve solo los taxones con los ids indicados', function (): void {
    $repo = app(TaxonRepositoryInterface::class);
    $t1 = crearTaxon('Morpho peleides', 'especie');
    $t2 = Taxon::crear(TaxonId::generar(), 'Lepidoptera', 'orden', 'Linnaeus', 1758);
    $t3 = Taxon::crear(TaxonId::generar(), 'Heliconius melpomene', 'especie', 'Linnaeus', 1758);
    $repo->guardar($t1);
    $repo->guardar($t2);
    $repo->guardar($t3);

    $resultado = $repo->buscarPorIds([(string) $t1->id(), (string) $t3->id()]);

    expect($resultado)->toHaveCount(2);
    $nombres = array_map(fn ($t) => $t->nombreCientifico(), $resultado);
    expect($nombres)->toContain('Morpho peleides')
        ->and($nombres)->toContain('Heliconius melpomene');
});

test('buscarPorNombreYRango devuelve null cuando no existe', function (): void {
    $repo = app(TaxonRepositoryInterface::class);

    $resultado = $repo->buscarPorNombreYRango('Inexistente', RangoTaxonomico::Especie);

    expect($resultado)->toBeNull();
});
