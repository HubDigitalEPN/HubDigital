<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarPermiso\RegistrarPermisoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarPermiso\RegistrarPermisoInput;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryPermisoRepository;

test('registra un permiso de investigación', function (): void {
    $repo = new InMemoryPermisoRepository;
    $handler = new RegistrarPermisoHandler($repo);

    $output = $handler->handle(new RegistrarPermisoInput(
        tipo: 'research',
        numero: 'INV-2026-001',
        responsable: 'Dr. Juan Pérez',
        detalles: 'Permiso de investigación entomológica.',
    ));

    expect($output->tipo)->toBe('research')
        ->and($output->numero)->toBe('INV-2026-001')
        ->and($output->responsable)->toBe('Dr. Juan Pérez')
        ->and($repo->buscarPorId($output->id))->not->toBeNull();
});

test('registra varios permisos de distintos tipos', function (): void {
    $repo = new InMemoryPermisoRepository;
    $handler = new RegistrarPermisoHandler($repo);

    $handler->handle(new RegistrarPermisoInput(tipo: 'research'));
    $handler->handle(new RegistrarPermisoInput(tipo: 'transport'));
    $handler->handle(new RegistrarPermisoInput(tipo: 'export_import'));

    expect($repo->contarTodos())->toBe(3);
});

test('rechaza tipo desconocido', function (): void {
    $repo = new InMemoryPermisoRepository;

    (new RegistrarPermisoHandler($repo))->handle(new RegistrarPermisoInput(tipo: 'almacenamiento'));
})->throws(InvalidArgumentException::class, 'Tipo de permiso');

test('acepta alias en español para tipo', function (): void {
    $repo = new InMemoryPermisoRepository;

    $output = (new RegistrarPermisoHandler($repo))->handle(new RegistrarPermisoInput(tipo: 'transporte'));

    expect($output->tipo)->toBe('transport');
});
