<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\Coordenada;

test('crea una coordenada válida', function (): void {
    $coord = Coordenada::desde(-0.180653, -78.467834);

    expect($coord->latitud)->toBe(-0.180653)
        ->and($coord->longitud)->toBe(-78.467834);
});

test('acepta los valores extremos de latitud y longitud', function (float $lat, float $lon): void {
    $coord = Coordenada::desde($lat, $lon);

    expect($coord->latitud)->toBe($lat)
        ->and($coord->longitud)->toBe($lon);
})->with([
    [-90.0, -180.0],
    [90.0, 180.0],
    [0.0, 0.0],
]);

test('rechaza latitud fuera de rango', function (float $lat): void {
    Coordenada::desde($lat, 0.0);
})->throws(InvalidArgumentException::class, 'Latitud')->with([
    [-90.0001],
    [90.0001],
    [123.456],
    [-200.0],
]);

test('rechaza longitud fuera de rango', function (float $lon): void {
    Coordenada::desde(0.0, $lon);
})->throws(InvalidArgumentException::class, 'Longitud')->with([
    [-180.0001],
    [180.0001],
    [300.0],
    [-500.0],
]);

test('equals compara latitud y longitud', function (): void {
    $a = Coordenada::desde(-0.180653, -78.467834);
    $b = Coordenada::desde(-0.180653, -78.467834);
    $c = Coordenada::desde(-0.180654, -78.467834);

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});
