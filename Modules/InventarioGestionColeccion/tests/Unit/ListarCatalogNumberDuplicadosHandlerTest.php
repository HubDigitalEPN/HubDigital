<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCatalogNumberDuplicados\ListarCatalogNumberDuplicadosHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarCatalogNumberDuplicados\ListarCatalogNumberDuplicadosInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

function crearEspecimenConCatalogNumber(string $catalogNumber, string $fechaColecta = '2024-01-01'): Especimen
{
    return Especimen::crear(
        EspecimenId::generar(),
        'COD-'.bin2hex(random_bytes(4)),
        null,
        'X',
        $fechaColecta,
        'Colector',
        catalogNumber: $catalogNumber,
    );
}

test('lista catalog_numbers compartidos por 2+ especímenes', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $repo->guardar(crearEspecimenConCatalogNumber('1'));
    $repo->guardar(crearEspecimenConCatalogNumber('1'));
    $repo->guardar(crearEspecimenConCatalogNumber('2')); // único, no aparece

    $output = (new ListarCatalogNumberDuplicadosHandler($repo, new InMemoryTaxonRepository))->handle(
        new ListarCatalogNumberDuplicadosInput
    );

    expect($output->totalGrupos)->toBe(1)
        ->and($output->items[0]->catalogNumber)->toBe('1')
        ->and($output->items[0]->total)->toBe(2);
});

test('detecta cuando fechas son distintas (probable eventos legítimos)', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $repo->guardar(crearEspecimenConCatalogNumber('1', '1994-03-15'));
    $repo->guardar(crearEspecimenConCatalogNumber('1', '2006-07-21'));

    $output = (new ListarCatalogNumberDuplicadosHandler($repo, new InMemoryTaxonRepository))->handle(
        new ListarCatalogNumberDuplicadosInput
    );

    expect($output->items[0]->fechasDistintas)->toBeTrue();
});

test('detecta cuando fechas coinciden (probable error de catalogación)', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $repo->guardar(crearEspecimenConCatalogNumber('1', '2020-05-10'));
    $repo->guardar(crearEspecimenConCatalogNumber('1', '2020-05-10'));

    $output = (new ListarCatalogNumberDuplicadosHandler($repo, new InMemoryTaxonRepository))->handle(
        new ListarCatalogNumberDuplicadosInput
    );

    expect($output->items[0]->fechasDistintas)->toBeFalse();
});

test('ignora especímenes sin catalog_number', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $repo->guardar(Especimen::crear(EspecimenId::generar(), 'A', null, 'X', '2024-01-01', 'C'));
    $repo->guardar(Especimen::crear(EspecimenId::generar(), 'B', null, 'X', '2024-01-01', 'C'));

    $output = (new ListarCatalogNumberDuplicadosHandler($repo, new InMemoryTaxonRepository))->handle(
        new ListarCatalogNumberDuplicadosInput
    );

    expect($output->totalGrupos)->toBe(0);
});

test('ordena por tamaño de grupo descendente', function (): void {
    $repo = new InMemoryEspecimenRepository;
    foreach (['A', 'A', 'A', 'B', 'B'] as $cn) {
        $repo->guardar(crearEspecimenConCatalogNumber($cn));
    }

    $output = (new ListarCatalogNumberDuplicadosHandler($repo, new InMemoryTaxonRepository))->handle(
        new ListarCatalogNumberDuplicadosInput
    );

    expect($output->items[0]->catalogNumber)->toBe('A')
        ->and($output->items[0]->total)->toBe(3)
        ->and($output->items[1]->catalogNumber)->toBe('B');
});
