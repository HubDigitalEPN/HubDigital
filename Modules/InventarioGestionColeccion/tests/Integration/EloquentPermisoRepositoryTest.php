<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\PermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoPermiso;
use Modules\InventarioGestionColeccion\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('guarda y recupera un permiso con todos los campos', function (): void {
    $repo = app(PermisoRepositoryInterface::class);

    $permiso = Permiso::crear(
        id: $repo->nextIdentity(),
        tipo: 'research',
        numero: 'INV-2026-001',
        responsable: 'Dr. Juan Pérez',
        detalles: 'Permiso de investigación entomológica.',
    );

    $repo->guardar($permiso);

    $encontrado = $repo->buscarPorId($permiso->id());

    expect($encontrado)->not->toBeNull()
        ->and($encontrado->tipo())->toBe(TipoPermiso::Research)
        ->and($encontrado->numero())->toBe('INV-2026-001')
        ->and($encontrado->responsable())->toBe('Dr. Juan Pérez');
});

test('guarda un permiso mínimo sólo con tipo', function (): void {
    $repo = app(PermisoRepositoryInterface::class);

    $permiso = Permiso::crear(id: $repo->nextIdentity(), tipo: 'export_import');
    $repo->guardar($permiso);

    $encontrado = $repo->buscarPorId($permiso->id());

    expect($encontrado)->not->toBeNull()
        ->and($encontrado->tipo())->toBe(TipoPermiso::ExportImport)
        ->and($encontrado->numero())->toBeNull();
});

test('buscarPorTipo filtra correctamente', function (): void {
    $repo = app(PermisoRepositoryInterface::class);
    $repo->guardar(Permiso::crear($repo->nextIdentity(), 'research'));
    $repo->guardar(Permiso::crear($repo->nextIdentity(), 'transport'));
    $repo->guardar(Permiso::crear($repo->nextIdentity(), 'transport'));

    $transports = $repo->buscarPorTipo(TipoPermiso::Transport);

    expect($transports)->toHaveCount(2);
});

test('contarTodos refleja el número de permisos guardados', function (): void {
    $repo = app(PermisoRepositoryInterface::class);
    $repo->guardar(Permiso::crear($repo->nextIdentity(), 'research'));
    $repo->guardar(Permiso::crear($repo->nextIdentity(), 'transport'));

    expect($repo->contarTodos())->toBe(2);
});
