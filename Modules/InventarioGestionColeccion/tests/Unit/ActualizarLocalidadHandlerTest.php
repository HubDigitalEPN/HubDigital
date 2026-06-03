<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarLocalidad\ActualizarLocalidadHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ActualizarLocalidad\ActualizarLocalidadInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryLocalidadRepository;

test('actualiza una localidad existente', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $l = Localidad::crear(LocalidadId::generar(), 'Pichincha', 'provincia');
    $repo->guardar($l);

    $output = (new ActualizarLocalidadHandler($repo))->handle(new ActualizarLocalidadInput(
        localidadId: (string) $l->id(),
        nombreCanonico: 'Pichincha (revisada)',
        rango: 'provincia',
        country: 'Ecuador',
    ));

    expect($output->nombreCanonico)->toBe('Pichincha (revisada)')
        ->and($output->country)->toBe('Ecuador');

    $persistida = $repo->buscarPorId($l->id());
    expect($persistida->nombreCanonico())->toBe('Pichincha (revisada)');
});

test('rechaza actualizar una localidad inexistente', function (): void {
    $repo = new InMemoryLocalidadRepository;

    (new ActualizarLocalidadHandler($repo))->handle(new ActualizarLocalidadInput(
        localidadId: (string) LocalidadId::generar(),
        nombreCanonico: 'X',
        rango: 'sitio',
    ));
})->throws(DomainException::class, 'no encontrada');

test('rechaza que la localidad sea su propio padre', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $l = Localidad::crear(LocalidadId::generar(), 'X', 'provincia');
    $repo->guardar($l);

    (new ActualizarLocalidadHandler($repo))->handle(new ActualizarLocalidadInput(
        localidadId: (string) $l->id(),
        nombreCanonico: 'X',
        rango: 'provincia',
        padreId: (string) $l->id(),
    ));
})->throws(DomainException::class, 'propio padre');

test('permite cambiar el padre por uno con rango admisible', function (): void {
    $repo = new InMemoryLocalidadRepository;
    $pais = Localidad::crear(LocalidadId::generar(), 'Ecuador', 'pais');
    $repo->guardar($pais);
    $prov = Localidad::crear(LocalidadId::generar(), 'Pichincha', 'provincia');
    $repo->guardar($prov);

    $output = (new ActualizarLocalidadHandler($repo))->handle(new ActualizarLocalidadInput(
        localidadId: (string) $prov->id(),
        nombreCanonico: 'Pichincha',
        rango: 'provincia',
        padreId: (string) $pais->id(),
    ));

    expect($output->padreId)->toBe((string) $pais->id());
});
