<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AsociarPermisoAEspecimen\AsociarPermisoAEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\AsociarPermisoAEspecimen\AsociarPermisoAEspecimenInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PermisoId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenPermisoRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryPermisoRepository;

function bootstrapAsociarPermisoHandler(): array
{
    $pivoteRepo = new InMemoryEspecimenPermisoRepository;
    $especimenRepo = new InMemoryEspecimenRepository;
    $permisoRepo = new InMemoryPermisoRepository;
    $handler = new AsociarPermisoAEspecimenHandler($pivoteRepo, $especimenRepo, $permisoRepo);

    return [$handler, $pivoteRepo, $especimenRepo, $permisoRepo];
}

test('asocia un permiso a un espécimen existente', function (): void {
    [$handler, $pivoteRepo, $especimenRepo, $permisoRepo] = bootstrapAsociarPermisoHandler();
    $especimen = Especimen::crear(EspecimenId::generar(), 'MEPN-INV-1', null, 'X', '2024-01-01', 'C');
    $permiso = Permiso::crear(PermisoId::generar(), 'research', 'INV-2026-001');
    $especimenRepo->guardar($especimen);
    $permisoRepo->guardar($permiso);

    $output = $handler->handle(new AsociarPermisoAEspecimenInput(
        especimenId: (string) $especimen->id(),
        permisoId: (string) $permiso->id(),
    ));

    expect($output->yaExistia)->toBeFalse()
        ->and($pivoteRepo->existeAsociacion($especimen->id(), $permiso->id()))->toBeTrue()
        ->and($pivoteRepo->listarPermisosDe($especimen->id()))->toHaveCount(1);
});

test('asociar dos veces el mismo permiso es idempotente', function (): void {
    [$handler, $pivoteRepo, $especimenRepo, $permisoRepo] = bootstrapAsociarPermisoHandler();
    $especimen = Especimen::crear(EspecimenId::generar(), 'MEPN-INV-1', null, 'X', '2024-01-01', 'C');
    $permiso = Permiso::crear(PermisoId::generar(), 'research');
    $especimenRepo->guardar($especimen);
    $permisoRepo->guardar($permiso);

    $handler->handle(new AsociarPermisoAEspecimenInput(
        especimenId: (string) $especimen->id(),
        permisoId: (string) $permiso->id(),
    ));
    $segundo = $handler->handle(new AsociarPermisoAEspecimenInput(
        especimenId: (string) $especimen->id(),
        permisoId: (string) $permiso->id(),
    ));

    expect($segundo->yaExistia)->toBeTrue()
        ->and($pivoteRepo->listarPermisosDe($especimen->id()))->toHaveCount(1);
});

test('rechaza espécimen inexistente', function (): void {
    [$handler, , , $permisoRepo] = bootstrapAsociarPermisoHandler();
    $permiso = Permiso::crear(PermisoId::generar(), 'research');
    $permisoRepo->guardar($permiso);

    $handler->handle(new AsociarPermisoAEspecimenInput(
        especimenId: (string) EspecimenId::generar(),
        permisoId: (string) $permiso->id(),
    ));
})->throws(DomainException::class, 'Espécimen');

test('rechaza permiso inexistente', function (): void {
    [$handler, , $especimenRepo] = bootstrapAsociarPermisoHandler();
    $especimen = Especimen::crear(EspecimenId::generar(), 'MEPN-INV-1', null, 'X', '2024-01-01', 'C');
    $especimenRepo->guardar($especimen);

    $handler->handle(new AsociarPermisoAEspecimenInput(
        especimenId: (string) $especimen->id(),
        permisoId: (string) PermisoId::generar(),
    ));
})->throws(DomainException::class, 'Permiso');

test('un permiso puede cubrir múltiples espécimenes', function (): void {
    [$handler, $pivoteRepo, $especimenRepo, $permisoRepo] = bootstrapAsociarPermisoHandler();
    $permiso = Permiso::crear(PermisoId::generar(), 'export_import');
    $permisoRepo->guardar($permiso);

    foreach (['E1', 'E2', 'E3'] as $codigo) {
        $e = Especimen::crear(EspecimenId::generar(), $codigo, null, 'X', '2024-01-01', 'C');
        $especimenRepo->guardar($e);
        $handler->handle(new AsociarPermisoAEspecimenInput(
            especimenId: (string) $e->id(),
            permisoId: (string) $permiso->id(),
        ));
    }

    expect($pivoteRepo->listarEspecimenesDe($permiso->id()))->toHaveCount(3);
});
