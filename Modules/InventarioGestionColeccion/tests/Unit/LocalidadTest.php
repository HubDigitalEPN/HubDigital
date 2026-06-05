<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;

test('crear una localidad mínima con nombre y rango', function (): void {
    $localidad = Localidad::crear(LocalidadId::generar(), 'Ecuador', 'pais');

    expect($localidad->nombreCanonico())->toBe('Ecuador')
        ->and($localidad->rango())->toBe(RangoLocalidad::Pais)
        ->and($localidad->padreId())->toBeNull()
        ->and($localidad->coordenada())->toBeNull()
        ->and($localidad->country())->toBeNull();
});

test('crear con coordenadas válidas las almacena', function (): void {
    $localidad = Localidad::crear(
        id: LocalidadId::generar(),
        nombreCanonico: 'Estación Yasuní',
        rango: 'sitio',
        latitud: -0.6745,
        longitud: -76.3961,
    );

    expect($localidad->coordenada()?->latitud)->toBe(-0.6745)
        ->and($localidad->coordenada()?->longitud)->toBe(-76.3961);
});

test('crear con sólo latitud (o sólo longitud) lanza excepción', function (): void {
    Localidad::crear(
        id: LocalidadId::generar(),
        nombreCanonico: 'X',
        rango: 'sitio',
        latitud: -1.5,
    );
})->throws(InvalidArgumentException::class, 'latitud como longitud');

test('crear con coordenadas inválidas propaga el error de Coordenada', function (): void {
    Localidad::crear(
        id: LocalidadId::generar(),
        nombreCanonico: 'X',
        rango: 'sitio',
        latitud: 200.0,
        longitud: 0.0,
    );
})->throws(InvalidArgumentException::class, 'Latitud');

test('crear con rango inválido lanza excepción', function (): void {
    Localidad::crear(LocalidadId::generar(), 'X', 'rango_inexistente');
})->throws(InvalidArgumentException::class, 'Rango de localidad');

test('crear con nombre vacío lanza excepción', function (): void {
    Localidad::crear(LocalidadId::generar(), '   ', 'sitio');
})->throws(InvalidArgumentException::class, 'nombre canónico');

test('crear acepta alias de rango (country)', function (): void {
    $l = Localidad::crear(LocalidadId::generar(), 'Ecuador', 'country');

    expect($l->rango())->toBe(RangoLocalidad::Pais);
});

test('crear normaliza opcionales vacíos a null', function (): void {
    $l = Localidad::crear(
        id: LocalidadId::generar(),
        nombreCanonico: 'X',
        rango: 'sitio',
        country: '   ',
        stateProvince: '',
    );

    expect($l->country())->toBeNull()
        ->and($l->stateProvince())->toBeNull();
});

test('actualizar modifica los campos de la localidad', function (): void {
    $l = Localidad::crear(LocalidadId::generar(), 'Pichincha', 'provincia');

    $l->actualizar(
        nombreCanonico: 'Pichincha (actualizado)',
        rango: 'provincia',
        country: 'Ecuador',
    );

    expect($l->nombreCanonico())->toBe('Pichincha (actualizado)')
        ->and($l->country())->toBe('Ecuador');
});

test('crear con padre_id válido lo almacena', function (): void {
    $padreId = LocalidadId::generar();

    $l = Localidad::crear(
        id: LocalidadId::generar(),
        nombreCanonico: 'Pichincha',
        rango: 'provincia',
        padreId: (string) $padreId,
    );

    expect((string) $l->padreId())->toBe((string) $padreId);
});
