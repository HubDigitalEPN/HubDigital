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

test('crear conserva campos Darwin Core y ubicación estructurada', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'Parque Nacional Yasuní',
        '2018-02-01',
        'Lauren O\'Connell',
        occurrenceId: 'MEPN:INV:1',
        catalogNumber: '1',
        oldCode: '560',
        individualCount: 8,
        preparations: 'ethanol',
        disposition: 'En colección',
        occurrenceStatus: 'present',
        country: 'Ecuador',
        stateProvince: 'Orellana',
        municipality: 'Aguarico',
        localityName: 'Parque Nacional Yasuní, Onkonegare',
        decimalLatitude: -0.658,
        decimalLongitude: -76.452,
        geodeticDatum: 'WGS84',
        elevationInMeters: 216.3,
        biome: 'Amazonia',
        habitat: 'Bosque de tierra firme',
    );

    expect($especimen->occurrenceId())->toBe('MEPN:INV:1')
        ->and($especimen->catalogNumber())->toBe('1')
        ->and($especimen->individualCount())->toBe(8)
        ->and($especimen->preparations())->toBe('ethanol')
        ->and($especimen->country())->toBe('Ecuador')
        ->and($especimen->localityName())->toBe('Parque Nacional Yasuní, Onkonegare')
        ->and($especimen->decimalLatitude())->toBe(-0.658)
        ->and($especimen->decimalLongitude())->toBe(-76.452);
});

test('crear genera identificadores múltiples desde los códigos conocidos', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'Quito',
        '2024-01-01',
        'Colector',
        occurrenceId: 'MEPN:INV:1',
        catalogNumber: '1',
        oldCode: '560',
        cardexLiquidCollectionCode: 'MIA-33256',
    );

    $identificadores = array_map(fn ($i) => $i->toArray(), $especimen->identificadores());

    expect($identificadores)->toContain(['tipo' => 'codigo_catalogo', 'valor' => 'MEPN-INV-1'])
        ->and($identificadores)->toContain(['tipo' => 'occurrence_id', 'valor' => 'MEPN:INV:1'])
        ->and($identificadores)->toContain(['tipo' => 'catalog_number', 'valor' => '1'])
        ->and($identificadores)->toContain(['tipo' => 'old_code', 'valor' => '560'])
        ->and($identificadores)->toContain(['tipo' => 'cardex_liquid_collection_code', 'valor' => 'MIA-33256']);
});
