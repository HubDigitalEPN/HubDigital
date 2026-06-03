<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;
use Modules\InventarioGestionColeccion\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('guarda y recupera una localidad con todos los campos', function (): void {
    $repo = app(LocalidadRepositoryInterface::class);

    $localidad = Localidad::crear(
        id: $repo->nextIdentity(),
        nombreCanonico: 'Estación Yasuní',
        rango: 'sitio',
        latitud: -0.6745,
        longitud: -76.3961,
        geodeticDatum: 'WGS84',
        coordinateUncertaintyM: 30.0,
        latLonVerbatim: '-0.6745, -76.3961',
        country: 'Ecuador',
        stateProvince: 'Orellana',
        municipality: 'Aguarico',
    );

    $repo->guardar($localidad);

    $encontrada = $repo->buscarPorId($localidad->id());

    expect($encontrada)->not->toBeNull()
        ->and($encontrada->nombreCanonico())->toBe('Estación Yasuní')
        ->and($encontrada->rango())->toBe(RangoLocalidad::Sitio)
        ->and($encontrada->coordenada()?->latitud)->toBe(-0.6745)
        ->and($encontrada->coordenada()?->longitud)->toBe(-76.3961)
        ->and($encontrada->geodeticDatum())->toBe('WGS84')
        ->and($encontrada->coordinateUncertaintyM())->toBe(30.0)
        ->and($encontrada->country())->toBe('Ecuador')
        ->and($encontrada->stateProvince())->toBe('Orellana');
});

test('guarda una localidad mínima sin coordenadas', function (): void {
    $repo = app(LocalidadRepositoryInterface::class);

    $localidad = Localidad::crear($repo->nextIdentity(), 'Ecuador', 'pais');
    $repo->guardar($localidad);

    $encontrada = $repo->buscarPorId($localidad->id());

    expect($encontrada->coordenada())->toBeNull()
        ->and($encontrada->country())->toBeNull();
});

test('buscarPorNombreYRango devuelve la coincidencia exacta', function (): void {
    $repo = app(LocalidadRepositoryInterface::class);

    $l = Localidad::crear($repo->nextIdentity(), 'Pichincha', 'provincia');
    $repo->guardar($l);

    $resultado = $repo->buscarPorNombreYRango('Pichincha', RangoLocalidad::Provincia);

    expect($resultado)->not->toBeNull()
        ->and((string) $resultado->id())->toBe((string) $l->id());
});

test('listarPagina respeta offset y limit ordenado por nombre', function (): void {
    $repo = app(LocalidadRepositoryInterface::class);
    foreach (['Carchi', 'Azuay', 'Bolívar'] as $nombre) {
        $repo->guardar(Localidad::crear($repo->nextIdentity(), $nombre, 'provincia'));
    }

    $pagina = $repo->listarPagina(0, 2);

    expect($pagina)->toHaveCount(2)
        ->and($pagina[0]->nombreCanonico())->toBe('Azuay')
        ->and($pagina[1]->nombreCanonico())->toBe('Bolívar');
});

test('contarTodos cuenta el total de localidades', function (): void {
    $repo = app(LocalidadRepositoryInterface::class);
    foreach (['A', 'B', 'C'] as $nombre) {
        $repo->guardar(Localidad::crear($repo->nextIdentity(), $nombre, 'sitio'));
    }

    expect($repo->contarTodos())->toBe(3);
});

test('jerarquía padre/hijo se persiste correctamente', function (): void {
    $repo = app(LocalidadRepositoryInterface::class);

    $pais = Localidad::crear($repo->nextIdentity(), 'Ecuador', 'pais');
    $repo->guardar($pais);

    $prov = Localidad::crear(
        id: $repo->nextIdentity(),
        nombreCanonico: 'Pichincha',
        rango: 'provincia',
        padreId: (string) $pais->id(),
    );
    $repo->guardar($prov);

    $encontrada = $repo->buscarPorId($prov->id());

    expect((string) $encontrada->padreId())->toBe((string) $pais->id());
});
