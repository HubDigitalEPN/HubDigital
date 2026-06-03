<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Permiso;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenPermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\PermisoRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function sembrarEspecimenYPermiso(): array
{
    $taxonRepo = app(TaxonRepositoryInterface::class);
    $especimenRepo = app(EspecimenRepositoryInterface::class);
    $permisoRepo = app(PermisoRepositoryInterface::class);

    $taxon = Taxon::crear($taxonRepo->nextIdentity(), 'Morpho peleides', 'especie', 'Kollar', 1850);
    $taxonRepo->guardar($taxon);

    $especimen = Especimen::crear(
        $especimenRepo->nextIdentity(),
        'MEPN-INV-test-'.bin2hex(random_bytes(4)),
        (string) $taxon->id(),
        'Pichincha',
        '2024-01-01',
        'Colector',
    );
    $especimenRepo->guardar($especimen);

    $permiso = Permiso::crear($permisoRepo->nextIdentity(), 'research', 'INV-2026-001');
    $permisoRepo->guardar($permiso);

    return [$especimen, $permiso];
}

test('asocia y existeAsociacion confirma la fila en el pivote', function (): void {
    $pivoteRepo = app(EspecimenPermisoRepositoryInterface::class);
    [$especimen, $permiso] = sembrarEspecimenYPermiso();

    $pivoteRepo->asociar($especimen->id(), $permiso->id());

    expect($pivoteRepo->existeAsociacion($especimen->id(), $permiso->id()))->toBeTrue();
});

test('asociar dos veces no duplica filas (idempotente)', function (): void {
    $pivoteRepo = app(EspecimenPermisoRepositoryInterface::class);
    [$especimen, $permiso] = sembrarEspecimenYPermiso();

    $pivoteRepo->asociar($especimen->id(), $permiso->id());
    $pivoteRepo->asociar($especimen->id(), $permiso->id());

    expect($pivoteRepo->listarPermisosDe($especimen->id()))->toHaveCount(1);
});

test('desasociar elimina la fila del pivote', function (): void {
    $pivoteRepo = app(EspecimenPermisoRepositoryInterface::class);
    [$especimen, $permiso] = sembrarEspecimenYPermiso();
    $pivoteRepo->asociar($especimen->id(), $permiso->id());

    $pivoteRepo->desasociar($especimen->id(), $permiso->id());

    expect($pivoteRepo->existeAsociacion($especimen->id(), $permiso->id()))->toBeFalse();
});

test('listarPermisosDe devuelve todos los permisos asociados', function (): void {
    $pivoteRepo = app(EspecimenPermisoRepositoryInterface::class);
    $permisoRepo = app(PermisoRepositoryInterface::class);
    [$especimen, $permisoA] = sembrarEspecimenYPermiso();

    $permisoB = Permiso::crear($permisoRepo->nextIdentity(), 'transport', 'TRA-2026-001');
    $permisoRepo->guardar($permisoB);

    $pivoteRepo->asociar($especimen->id(), $permisoA->id());
    $pivoteRepo->asociar($especimen->id(), $permisoB->id());

    expect($pivoteRepo->listarPermisosDe($especimen->id()))->toHaveCount(2);
});
