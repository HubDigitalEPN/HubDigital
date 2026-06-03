<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes\ListarLocalidadVerbatimsPendientesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarLocalidadVerbatimsPendientes\ListarLocalidadVerbatimsPendientesInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryLocalidadRepository;

test('agrupa verbatims pendientes contando especímenes y propone candidatos', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $localidadRepo = new InMemoryLocalidadRepository;

    $yasuni = Localidad::crear(LocalidadId::generar(), 'Yasuní', 'area_protegida');
    $localidadRepo->guardar($yasuni);
    $localidadRepo->guardar(Localidad::crear(LocalidadId::generar(), 'Pichincha', 'provincia'));

    foreach (['Yasuní, Onkonegare', 'Yasuní, Onkonegare', 'Yasuní, Onkonegare'] as $verbatim) {
        $e = Especimen::crear(
            EspecimenId::generar(),
            'COD-'.bin2hex(random_bytes(4)),
            null,
            'X',
            '2024-01-01',
            'C',
            localidadVerbatim: $verbatim,
        );
        $especimenRepo->guardar($e);
    }
    // Un espécimen ya enlazado (no debe contarse)
    $enlazado = Especimen::crear(
        EspecimenId::generar(),
        'COD-OK',
        null,
        'X',
        '2024-01-01',
        'C',
        localidadVerbatim: 'Yasuní, Onkonegare',
    );
    $enlazado->enlazarLocalidad($yasuni->id());
    $especimenRepo->guardar($enlazado);

    $output = (new ListarLocalidadVerbatimsPendientesHandler($especimenRepo, $localidadRepo))->handle(
        new ListarLocalidadVerbatimsPendientesInput
    );

    expect($output->totalVerbatimsDistintos)->toBe(1)
        ->and($output->items[0]->verbatim)->toBe('Yasuní, Onkonegare')
        ->and($output->items[0]->totalEspecimenes)->toBe(3)
        ->and($output->items[0]->candidatos)->not->toBeEmpty();

    expect($output->items[0]->candidatos[0]->nombreCanonico)->toBe('Yasuní');
});

test('ignora especímenes sin localidad_verbatim', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $localidadRepo = new InMemoryLocalidadRepository;

    $especimenRepo->guardar(Especimen::crear(
        EspecimenId::generar(),
        'COD-1',
        null,
        'X',
        '2024-01-01',
        'C',
    ));

    $output = (new ListarLocalidadVerbatimsPendientesHandler($especimenRepo, $localidadRepo))->handle(
        new ListarLocalidadVerbatimsPendientesInput
    );

    expect($output->totalVerbatimsDistintos)->toBe(0);
});

test('ordena verbatims por frecuencia descendente', function (): void {
    $especimenRepo = new InMemoryEspecimenRepository;
    $localidadRepo = new InMemoryLocalidadRepository;

    $crear = fn (string $verbatim) => Especimen::crear(
        EspecimenId::generar(),
        'COD-'.bin2hex(random_bytes(4)),
        null,
        'X',
        '2024-01-01',
        'C',
        localidadVerbatim: $verbatim,
    );

    foreach (['A', 'A', 'A', 'B', 'B', 'C'] as $v) {
        $especimenRepo->guardar($crear($v));
    }

    $output = (new ListarLocalidadVerbatimsPendientesHandler($especimenRepo, $localidadRepo))->handle(
        new ListarLocalidadVerbatimsPendientesInput
    );

    expect($output->items[0]->verbatim)->toBe('A')
        ->and($output->items[0]->totalEspecimenes)->toBe(3)
        ->and($output->items[1]->verbatim)->toBe('B')
        ->and($output->items[1]->totalEspecimenes)->toBe(2)
        ->and($output->items[2]->verbatim)->toBe('C')
        ->and($output->items[2]->totalEspecimenes)->toBe(1);
});
