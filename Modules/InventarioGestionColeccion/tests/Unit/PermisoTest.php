<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoPermiso;

test('crear un permiso con datos mínimos lo deja consistente', function (): void {
    $permiso = Permiso::crear(
        id: PermisoId::generar(),
        tipo: 'research',
    );

    expect($permiso->tipo())->toBe(TipoPermiso::Research)
        ->and($permiso->numero())->toBeNull()
        ->and($permiso->responsable())->toBeNull()
        ->and($permiso->detalles())->toBeNull();
});

test('crear con todos los campos los persiste y normaliza', function (): void {
    $permiso = Permiso::crear(
        id: PermisoId::generar(),
        tipo: 'export_import',
        numero: 'EXP-2026-0123',
        responsable: '  Dr. Juan Pérez  ',
        detalles: '  Permiso para exportación a museo extranjero  ',
    );

    expect($permiso->tipo())->toBe(TipoPermiso::ExportImport)
        ->and($permiso->numero())->toBe('EXP-2026-0123')
        ->and($permiso->responsable())->toBe('Dr. Juan Pérez')
        ->and($permiso->detalles())->toBe('Permiso para exportación a museo extranjero');
});

test('crear con tipo inválido lanza InvalidArgumentException', function (): void {
    Permiso::crear(PermisoId::generar(), 'almacenamiento');
})->throws(InvalidArgumentException::class, 'Tipo de permiso');

test('crear acepta alias en español para tipo', function (): void {
    $permiso = Permiso::crear(PermisoId::generar(), 'investigación');

    expect($permiso->tipo())->toBe(TipoPermiso::Research);
});

test('crear con campos opcionales vacíos los normaliza a null', function (?string $valor): void {
    $permiso = Permiso::crear(
        id: PermisoId::generar(),
        tipo: 'transport',
        numero: $valor,
        responsable: $valor,
        detalles: $valor,
    );

    expect($permiso->numero())->toBeNull()
        ->and($permiso->responsable())->toBeNull()
        ->and($permiso->detalles())->toBeNull();
})->with([null, '', '   ']);

test('actualizar cambia los campos', function (): void {
    $permiso = Permiso::crear(PermisoId::generar(), 'research', 'INV-1');

    $permiso->actualizar('transport', 'TRA-9', 'Otro responsable', 'detalles nuevos');

    expect($permiso->tipo())->toBe(TipoPermiso::Transport)
        ->and($permiso->numero())->toBe('TRA-9')
        ->and($permiso->responsable())->toBe('Otro responsable')
        ->and($permiso->detalles())->toBe('detalles nuevos');
});

test('el id asignado en crear se conserva', function (): void {
    $id = PermisoId::generar();
    $permiso = Permiso::crear($id, 'research');

    expect((string) $permiso->id())->toBe((string) $id);
});
