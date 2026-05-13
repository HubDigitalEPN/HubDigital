<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EntidadDepositanteId;
use Modules\InventarioGestionColeccion\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function crearEntidad(string $nombre = 'Universidad Central', string $tipo = 'institucion'): EntidadDepositante
{
    return EntidadDepositante::crear(
        EntidadDepositanteId::generar(),
        $nombre,
        $tipo,
        'contacto@ejemplo.com',
    );
}

test('guardar y recuperar una entidad depositante por id', function (): void {
    $repo = app(EntidadDepositanteRepositoryInterface::class);
    $entidad = crearEntidad();

    $repo->guardar($entidad);

    $encontrada = $repo->buscarPorId($entidad->id());

    expect($encontrada)->not->toBeNull()
        ->and($encontrada->nombre())->toBe('Universidad Central')
        ->and($encontrada->tipo()->value)->toBe('institucion');
});

test('nextIdentity devuelve un EntidadDepositanteId unico', function (): void {
    $repo = app(EntidadDepositanteRepositoryInterface::class);

    $id1 = $repo->nextIdentity();
    $id2 = $repo->nextIdentity();

    expect((string) $id1)->not->toBe((string) $id2);
});

test('guardar actualiza una entidad existente', function (): void {
    $repo = app(EntidadDepositanteRepositoryInterface::class);
    $entidad = crearEntidad();
    $repo->guardar($entidad);

    $entidad->actualizar('Dr. Juan Pérez', 'persona', 'juan@correo.com');
    $repo->guardar($entidad);

    $encontrada = $repo->buscarPorId($entidad->id());

    expect($encontrada->nombre())->toBe('Dr. Juan Pérez')
        ->and($encontrada->tipo()->value)->toBe('persona');
});

test('buscarPorNombre devuelve la entidad correcta', function (): void {
    $repo = app(EntidadDepositanteRepositoryInterface::class);
    $repo->guardar(crearEntidad('Universidad Central'));
    $repo->guardar(crearEntidad('Instituto Nacional'));

    $encontrada = $repo->buscarPorNombre('Instituto Nacional');

    expect($encontrada)->not->toBeNull()
        ->and($encontrada->nombre())->toBe('Instituto Nacional');
});

test('buscarPorNombre devuelve null cuando no existe', function (): void {
    $repo = app(EntidadDepositanteRepositoryInterface::class);

    expect($repo->buscarPorNombre('Inexistente'))->toBeNull();
});

test('buscarTodas devuelve todas las entidades', function (): void {
    $repo = app(EntidadDepositanteRepositoryInterface::class);
    $repo->guardar(crearEntidad('Universidad Central'));
    $repo->guardar(crearEntidad('Instituto Nacional'));

    $resultado = $repo->buscarTodas();

    expect($resultado)->toHaveCount(2);
});
