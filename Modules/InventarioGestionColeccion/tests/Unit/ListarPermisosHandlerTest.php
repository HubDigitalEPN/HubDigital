<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarPermisos\ListarPermisosHandler;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryPermisoRepository;

test('listar repositorio vacío devuelve total 0', function (): void {
    $repo = new InMemoryPermisoRepository;

    $output = (new ListarPermisosHandler($repo))->handle();

    expect($output->total)->toBe(0)
        ->and($output->items)->toBe([]);
});

test('listar devuelve todos los permisos como items', function (): void {
    $repo = new InMemoryPermisoRepository;
    $repo->guardar(Permiso::crear(PermisoId::generar(), 'research', 'INV-1'));
    $repo->guardar(Permiso::crear(PermisoId::generar(), 'transport', 'TRA-1'));

    $output = (new ListarPermisosHandler($repo))->handle();

    expect($output->total)->toBe(2)
        ->and($output->items)->toHaveCount(2)
        ->and($output->items[0]->tipo)->toBeIn(['research', 'transport']);
});
