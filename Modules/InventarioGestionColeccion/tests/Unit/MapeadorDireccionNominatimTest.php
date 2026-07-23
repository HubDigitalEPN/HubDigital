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

test('resuelve la provincia desde el código ISO 3166-2 cuando falta state (DMQ)', function (): void {
    // Caso real: Nominatim a zoom 12 en Quito NO devuelve `state`; la provincia
    // solo llega como ISO3166-2-lvl4 = EC-P (Pichincha).
    $u = MapeadorDireccionNominatim::desdeRespuesta([
        'display_name' => 'Iñaquito, Distrito Metropolitano de Quito, Pichincha, Ecuador',
        'address' => [
            'city_district' => 'Iñaquito',
            'county' => 'Distrito Metropolitano de Quito',
            'ISO3166-2-lvl4' => 'EC-P',
            'country' => 'Ecuador',
            'country_code' => 'ec',
        ],
    ]);

    expect($u->country)->toBe('Ecuador')
        ->and($u->stateProvince)->toBe('Pichincha')
        ->and($u->municipality)->toBe('Distrito Metropolitano de Quito');
});

test('el state explícito tiene prioridad sobre el código ISO', function (): void {
    $u = MapeadorDireccionNominatim::desdeRespuesta([
        'display_name' => 'X, Guayas, Ecuador',
        'address' => [
            'state' => 'Guayas',
            'ISO3166-2-lvl4' => 'EC-P', // incoherente a propósito: debe ganar el state
            'country' => 'Ecuador',
        ],
    ]);

    expect($u->stateProvince)->toBe('Guayas');
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
