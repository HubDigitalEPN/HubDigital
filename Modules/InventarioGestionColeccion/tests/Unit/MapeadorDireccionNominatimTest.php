<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters\MapeadorDireccionNominatim;

test('mapea una respuesta de Ecuador a los campos Darwin Core', function (): void {
    // Respuesta real de Nominatim /reverse (jsonv2) para un punto en Sucumbíos.
    $respuesta = [
        'display_name' => 'Parroquia Limoncocha, Shushufindi, Sucumbíos, Ecuador',
        'address' => [
            'municipality' => 'Parroquia Limoncocha',
            'county' => 'Shushufindi',
            'state' => 'Sucumbíos',
            'country' => 'Ecuador',
            'country_code' => 'ec',
        ],
    ];

    $u = MapeadorDireccionNominatim::desdeRespuesta($respuesta);

    expect($u->country)->toBe('Ecuador')
        ->and($u->stateProvince)->toBe('Sucumbíos')
        ->and($u->municipality)->toBe('Shushufindi') // county = cantón
        ->and($u->poblado)->toBe('Parroquia Limoncocha')
        ->and($u->displayName)->toBe('Parroquia Limoncocha, Shushufindi, Sucumbíos, Ecuador');
});

test('prefiere city/town como poblado cuando existen', function (): void {
    $u = MapeadorDireccionNominatim::desdeRespuesta([
        'display_name' => 'Tena, Napo, Ecuador',
        'address' => [
            'town' => 'Tena',
            'municipality' => 'Parroquia Tena',
            'county' => 'Tena',
            'state' => 'Napo',
            'country' => 'Ecuador',
        ],
    ]);

    expect($u->poblado)->toBe('Tena')
        ->and($u->stateProvince)->toBe('Napo')
        ->and($u->municipality)->toBe('Tena');
});

test('devuelve todo null cuando no hay address', function (): void {
    $u = MapeadorDireccionNominatim::desdeRespuesta(['error' => 'Unable to geocode']);

    expect($u->country)->toBeNull()
        ->and($u->stateProvince)->toBeNull()
        ->and($u->municipality)->toBeNull()
        ->and($u->poblado)->toBeNull()
        ->and($u->displayName)->toBeNull();
});

test('ignora cadenas vacías en address', function (): void {
    $u = MapeadorDireccionNominatim::desdeRespuesta([
        'address' => [
            'state' => '   ',
            'country' => 'Ecuador',
        ],
    ]);

    expect($u->country)->toBe('Ecuador')
        ->and($u->stateProvince)->toBeNull();
});
