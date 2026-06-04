<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerTaxonesParaVerbatim\ProponerTaxonesParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProponerTaxonesParaVerbatim\ProponerTaxonesParaVerbatimInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

test('propone taxones candidatos ordenados por similitud', function (): void {
    $repo = new InMemoryTaxonRepository;
    $repo->guardar(Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie', 'Kollar', 1850));
    $repo->guardar(Taxon::crear(TaxonId::generar(), 'Morpho menelaus', 'especie', 'Linnaeus', 1758));
    $repo->guardar(Taxon::crear(TaxonId::generar(), 'Heliconius melpomene', 'especie', 'Linnaeus', 1758));

    $output = (new ProponerTaxonesParaVerbatimHandler($repo))->handle(
        new ProponerTaxonesParaVerbatimInput(verbatim: 'Morpho peleides')
    );

    expect($output->items)->toHaveCount(2)
        ->and($output->items[0]->nombreCientifico)->toBe('Morpho peleides')
        ->and($output->items[0]->puntajeSimilitud)->toBeGreaterThan($output->items[1]->puntajeSimilitud);
});

test('una sola palabra busca por substring del primer fragmento', function (): void {
    $repo = new InMemoryTaxonRepository;
    $repo->guardar(Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie'));
    $repo->guardar(Taxon::crear(TaxonId::generar(), 'Heliconius melpomene', 'especie'));

    $output = (new ProponerTaxonesParaVerbatimHandler($repo))->handle(
        new ProponerTaxonesParaVerbatimInput(verbatim: 'Morpho')
    );

    expect($output->items)->toHaveCount(1)
        ->and($output->items[0]->nombreCientifico)->toBe('Morpho peleides');
});

test('verbatim vacío devuelve lista vacía', function (): void {
    $repo = new InMemoryTaxonRepository;
    $repo->guardar(Taxon::crear(TaxonId::generar(), 'Morpho peleides', 'especie'));

    $output = (new ProponerTaxonesParaVerbatimHandler($repo))->handle(
        new ProponerTaxonesParaVerbatimInput(verbatim: '   ')
    );

    expect($output->items)->toBe([]);
});

test('verbatim provisional tipo "Acragas_sp.1" busca por género', function (): void {
    $repo = new InMemoryTaxonRepository;
    $repo->guardar(Taxon::crear(TaxonId::generar(), 'Acragas', 'genero'));
    $repo->guardar(Taxon::crear(TaxonId::generar(), 'Acragas sp.1', 'morfoespecie'));

    $output = (new ProponerTaxonesParaVerbatimHandler($repo))->handle(
        new ProponerTaxonesParaVerbatimInput(verbatim: 'Acragas_sp.1')
    );

    expect($output->items)->toHaveCount(2);
});

test('limita el número de resultados', function (): void {
    $repo = new InMemoryTaxonRepository;
    foreach (['M. peleides', 'M. menelaus', 'M. amathonte', 'M. cypris', 'M. helenor'] as $n) {
        $repo->guardar(Taxon::crear(TaxonId::generar(), $n, 'especie'));
    }

    $output = (new ProponerTaxonesParaVerbatimHandler($repo))->handle(
        new ProponerTaxonesParaVerbatimInput(verbatim: 'M.', limite: 3)
    );

    expect($output->items)->toHaveCount(3);
});
