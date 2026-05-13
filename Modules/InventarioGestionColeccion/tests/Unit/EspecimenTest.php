<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;

test('crear un especimen lo deja en estado disponible', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'COLEOP-001',
        'taxon-uuid-dummy-0000-000000000001',
        'Pichincha',
        '2024-03-15',
        'Dr. Colector',
    );

    expect($especimen->estado())->toBe(EstadoEspecimen::Disponible)
        ->and($especimen->codigoCatalogo())->toBe('COLEOP-001')
        ->and($especimen->localidad())->toBe('Pichincha')
        ->and($especimen->fechaColecta())->toBe('2024-03-15')
        ->and($especimen->colector())->toBe('Dr. Colector')
        ->and($especimen->entidadDepositanteId())->toBeNull();
});

test('crear recorta espacios del codigo catalogo localidad y colector', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        '  COLEOP-001  ',
        'taxon-uuid-dummy-0000-000000000001',
        '  Pichincha  ',
        '2024-03-15',
        '  Dr. Colector  ',
    );

    expect($especimen->codigoCatalogo())->toBe('COLEOP-001')
        ->and($especimen->localidad())->toBe('Pichincha')
        ->and($especimen->colector())->toBe('Dr. Colector');
});

test('crear con entidad depositante la almacena', function (): void {
    $entidadId = 'entidad-uuid-0000-0000-000000000001';

    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'COLEOP-002',
        'taxon-uuid-dummy-0000-000000000001',
        'Imbabura',
        '2024-06-01',
        'Colector B',
        $entidadId,
    );

    expect($especimen->entidadDepositanteId())->toBe($entidadId);
});

test('marcarEnPrestamo cambia estado a en_prestamo', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'COLEOP-001',
        'taxon-uuid-dummy-0000-000000000001',
        'Pichincha',
        '2024-03-15',
        'Colector',
    );

    $especimen->marcarEnPrestamo();

    expect($especimen->estado())->toBe(EstadoEspecimen::EnPrestamo);
});

test('marcarDisponible desde en_prestamo restaura estado disponible', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'COLEOP-001',
        'taxon-uuid-dummy-0000-000000000001',
        'Pichincha',
        '2024-03-15',
        'Colector',
    );
    $especimen->marcarEnPrestamo();

    $especimen->marcarDisponible();

    expect($especimen->estado())->toBe(EstadoEspecimen::Disponible);
});

test('el id asignado en crear se conserva', function (): void {
    $id = EspecimenId::generar();
    $especimen = Especimen::crear($id, 'COLEOP-001', 'taxon-uuid-0000-0000-000000000001', 'Quito', '2024-01-01', 'Colector');

    expect((string) $especimen->id())->toBe((string) $id);
});
