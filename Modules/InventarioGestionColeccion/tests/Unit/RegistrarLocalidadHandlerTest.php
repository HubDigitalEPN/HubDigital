<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarLocalidad\RegistrarLocalidadHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarLocalidad\RegistrarLocalidadInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryLocalidadRepository;

test('registra una localidad raíz (sin padre)', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $output = (new RegistrarLocalidadHandler($repo))->handle(
        new RegistrarLocalidadInput(nombreCanonico: 'Ecuador', rango: 'pais', country: 'Ecuador')
    );

    expect($output->nombreCanonico)->toBe('Ecuador')
        ->and($output->rango)->toBe('pais')
        ->and($output->country)->toBe('Ecuador')
        ->and($repo->buscarPorId($output->id))->not->toBeNull();
});

test('rechaza duplicados de nombre + rango', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $handler = new RegistrarLocalidadHandler($repo);

    $handler->handle(new RegistrarLocalidadInput(nombreCanonico: 'Ecuador', rango: 'pais'));
    $handler->handle(new RegistrarLocalidadInput(nombreCanonico: 'Ecuador', rango: 'pais'));
})->throws(DomainException::class, 'Ya existe');

test('permite mismo nombre con rangos distintos', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $handler = new RegistrarLocalidadHandler($repo);

    $handler->handle(new RegistrarLocalidadInput(nombreCanonico: 'Sucumbíos', rango: 'provincia'));
    $handler->handle(new RegistrarLocalidadInput(nombreCanonico: 'Sucumbíos', rango: 'sector'));

    expect($repo->contarTodos())->toBe(2);
});

test('rechaza padre_id inexistente', function (): void {
    $repo = new InMemoryLocalidadRepository;

    (new RegistrarLocalidadHandler($repo))->handle(new RegistrarLocalidadInput(
        nombreCanonico: 'Pichincha',
        rango: 'provincia',
        padreId: (string) LocalidadId::generar(),
    ));
})->throws(DomainException::class, 'no encontrada');

test('rechaza padre con rango mayor (un sitio no puede ser padre de un país)', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $padre = Localidad::crear(LocalidadId::generar(), 'Estación Yasuní', 'sitio');
    $repo->guardar($padre);

    (new RegistrarLocalidadHandler($repo))->handle(new RegistrarLocalidadInput(
        nombreCanonico: 'Ecuador',
        rango: 'pais',
        padreId: (string) $padre->id(),
    ));
})->throws(DomainException::class, 'no puede ser padre');

test('acepta padre con rango menor (país como padre de provincia)', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $pais = Localidad::crear(LocalidadId::generar(), 'Ecuador', 'pais');
    $repo->guardar($pais);

    $output = (new RegistrarLocalidadHandler($repo))->handle(new RegistrarLocalidadInput(
        nombreCanonico: 'Pichincha',
        rango: 'provincia',
        padreId: (string) $pais->id(),
    ));

    expect($output->padreId)->toBe((string) $pais->id());
});

test('rechaza rango inválido', function (): void {
    $repo = new InMemoryLocalidadRepository;

    (new RegistrarLocalidadHandler($repo))->handle(new RegistrarLocalidadInput(
        nombreCanonico: 'X',
        rango: 'continente',
    ));
})->throws(InvalidArgumentException::class, 'Rango de localidad');

test('persiste coordenadas y verbatim', function (): void {
    $repo = new InMemoryLocalidadRepository;

    $output = (new RegistrarLocalidadHandler($repo))->handle(new RegistrarLocalidadInput(
        nombreCanonico: 'Estación Yasuní',
        rango: 'sitio',
        latitud: -0.6745,
        longitud: -76.3961,
        geodeticDatum: 'WGS84',
        latLonVerbatim: '-0.6745, -76.3961',
    ));

    expect($output->latitud)->toBe(-0.6745)
        ->and($output->longitud)->toBe(-76.3961)
        ->and($output->geodeticDatum)->toBe('WGS84')
        ->and($output->latLonVerbatim)->toBe('-0.6745, -76.3961');
});
