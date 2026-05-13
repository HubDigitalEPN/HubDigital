<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function crearEspecimenSimple(string $codigo = 'COLEOP-001', string $taxonId = ''): Especimen
{
    return Especimen::crear(
        EspecimenId::generar(),
        $codigo,
        $taxonId ?: 'taxon-'.str_pad('1', 32, '0', STR_PAD_LEFT),
        'Pichincha',
        '2024-03-15',
        'Dr. Colector',
    );
}

test('guardar y recuperar un especimen por id', function (): void {
    $repo = app(EspecimenRepositoryInterface::class);
    $especimen = crearEspecimenSimple();

    $repo->guardar($especimen);

    $encontrado = $repo->buscarPorId($especimen->id());

    expect($encontrado)->not->toBeNull()
        ->and($encontrado->codigoCatalogo())->toBe('COLEOP-001')
        ->and($encontrado->localidad())->toBe('Pichincha')
        ->and($encontrado->estado()->value)->toBe('disponible');
});

test('nextIdentity devuelve un EspecimenId unico', function (): void {
    $repo = app(EspecimenRepositoryInterface::class);

    $id1 = $repo->nextIdentity();
    $id2 = $repo->nextIdentity();

    expect((string) $id1)->not->toBe((string) $id2);
});

test('guardar actualiza un especimen existente', function (): void {
    $repo = app(EspecimenRepositoryInterface::class);
    $especimen = crearEspecimenSimple();
    $repo->guardar($especimen);

    $especimen->actualizar('Imbabura', '2025-01-01', 'Nuevo Colector', null);
    $repo->guardar($especimen);

    $encontrado = $repo->buscarPorId($especimen->id());

    expect($encontrado->localidad())->toBe('Imbabura')
        ->and($encontrado->colector())->toBe('Nuevo Colector');
});

test('buscarPorCodigoCatalogo devuelve el especimen correcto', function (): void {
    $repo = app(EspecimenRepositoryInterface::class);
    $repo->guardar(crearEspecimenSimple('COLEOP-001'));
    $repo->guardar(crearEspecimenSimple('DIPTER-002'));

    $encontrado = $repo->buscarPorCodigoCatalogo('DIPTER-002');

    expect($encontrado)->not->toBeNull()
        ->and($encontrado->codigoCatalogo())->toBe('DIPTER-002');
});

test('buscarPorCodigoCatalogo devuelve null cuando no existe', function (): void {
    $repo = app(EspecimenRepositoryInterface::class);

    expect($repo->buscarPorCodigoCatalogo('INEXISTENTE'))->toBeNull();
});

test('buscarPorLocalidad filtra por coincidencia parcial', function (): void {
    $repo = app(EspecimenRepositoryInterface::class);
    $e1 = crearEspecimenSimple('COLEOP-001');
    $e2 = Especimen::crear(EspecimenId::generar(), 'COLEOP-002', 'taxon-'.str_pad('1', 32, '0'), 'Imbabura', '2024-01-01', 'Colector');
    $repo->guardar($e1);
    $repo->guardar($e2);

    $resultado = $repo->buscarPorLocalidad('Pichincha');

    expect($resultado)->toHaveCount(1)
        ->and($resultado[0]->localidad())->toBe('Pichincha');
});

test('buscarPorEstado filtra correctamente', function (): void {
    $repo = app(EspecimenRepositoryInterface::class);
    $e1 = crearEspecimenSimple('COLEOP-001');
    $e2 = crearEspecimenSimple('COLEOP-002');
    $e2->marcarEnPrestamo();
    $repo->guardar($e1);
    $repo->guardar($e2);

    $disponibles = $repo->buscarPorEstado('disponible');
    $enPrestamo = $repo->buscarPorEstado('en_prestamo');

    expect($disponibles)->toHaveCount(1)
        ->and($enPrestamo)->toHaveCount(1);
});

test('buscarTodos devuelve todos los especimenes', function (): void {
    $repo = app(EspecimenRepositoryInterface::class);
    $repo->guardar(crearEspecimenSimple('COLEOP-001'));
    $repo->guardar(crearEspecimenSimple('COLEOP-002'));

    $resultado = $repo->buscarTodos();

    expect($resultado)->toHaveCount(2);
});
