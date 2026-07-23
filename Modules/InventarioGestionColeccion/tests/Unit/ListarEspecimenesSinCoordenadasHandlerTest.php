<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesSinCoordenadas\ListarEspecimenesSinCoordenadasHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEspecimenesSinCoordenadas\ListarEspecimenesSinCoordenadasInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryTaxonRepository;

function sembrarSinCoord(): ListarEspecimenesSinCoordenadasHandler
{
    $repo = new InMemoryEspecimenRepository;

    // Con coordenadas → NO debe aparecer.
    $repo->guardar(Especimen::crear(
        EspecimenId::generar(), 'CON-COORD', null, 'Yasuní', '2001-01-01', 'Ana',
        decimalLatitude: -0.6, decimalLongitude: -76.4,
    ));
    // Sin coordenadas pero con localidad → aparece, sinLocalidad=false.
    $repo->guardar(Especimen::crear(
        EspecimenId::generar(), 'SIN-COORD-1', null, 'Tena', '2001-01-01', 'Ana',
    ));
    // Sin coordenadas y sin localidad → aparece, sinLocalidad=true.
    $repo->guardar(Especimen::crear(
        EspecimenId::generar(), 'SIN-COORD-2', null, '', '2001-01-01', 'Ana',
        localityName: null,
    ));

    return new ListarEspecimenesSinCoordenadasHandler($repo, new InMemoryTaxonRepository);
}

test('lista solo los especímenes sin coordenadas', function (): void {
    $out = sembrarSinCoord()->handle(new ListarEspecimenesSinCoordenadasInput(page: 1, perPage: 20));

    expect($out->total)->toBe(2);
    $codigos = array_map(fn ($i) => $i['codigoCatalogo'], $out->items);
    expect($codigos)->not->toContain('CON-COORD')
        ->and($codigos)->toContain('SIN-COORD-1')
        ->and($codigos)->toContain('SIN-COORD-2');
});

test('marca sinLocalidad cuando no hay ni coordenadas ni localidad', function (): void {
    $out = sembrarSinCoord()->handle(new ListarEspecimenesSinCoordenadasInput(page: 1, perPage: 20));

    $porCodigo = [];
    foreach ($out->items as $i) {
        $porCodigo[$i['codigoCatalogo']] = $i;
    }

    expect($porCodigo['SIN-COORD-1']['sinLocalidad'])->toBeFalse()
        ->and($porCodigo['SIN-COORD-2']['sinLocalidad'])->toBeTrue();
});

test('pagina el conjunto sin coordenadas', function (): void {
    $out = sembrarSinCoord()->handle(new ListarEspecimenesSinCoordenadasInput(page: 1, perPage: 1));

    expect($out->total)->toBe(2)
        ->and($out->totalPaginas)->toBe(2)
        ->and($out->items)->toHaveCount(1);
});
