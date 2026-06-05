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
        elevationMinM: 216.3,
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

// ── P3: Campos verbatim, FK nullable y revisión ─────────────────────────────

test('crear acepta taxonId null (sin identificar)', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        taxonId: null,
        localidad: 'X',
        fechaColecta: '2024-01-01',
        colector: 'C',
        taxonVerbatim: 'Azteca_sp.7',
    );

    expect($especimen->taxonId())->toBeNull()
        ->and($especimen->taxonVerbatim())->toBe('Azteca_sp.7');
});

test('crear deja el espécimen en estado de revisión pendiente por defecto', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
    );

    expect($especimen->estadoRevision()->value)->toBe('pendiente')
        ->and($especimen->motivoRevision())->toBeNull();
});

test('crear conserva todos los campos verbatim provistos', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
        taxonVerbatim: 'Azteca sp.',
        localidadVerbatim: 'Yasuní, Onkonegare',
        fechaVerbatim: '21-Jul-01',
        fechaColectaFin: '2001-07-25',
        individualCountVerbatim: 'RBSO9-2',
        sex: 'female',
        lifeStage: 'adult',
        caste: 'worker',
        typeStatus: 'paratype',
        coordVerbatim: 'dañada',
        elevationMaxM: 250.0,
        microhabitat: 'hojarasca',
        biogeographicRegion: 'Amazonia',
        endemic: true,
        dnaNotes: 'CO1 secuenciado',
        occurrenceRemarks: 'colectado en trampa Berlese',
        taxonomicNotes: 'comparar con A. trigona',
        actaRecepcion: 'AR-2024-001',
    );

    expect($especimen->taxonVerbatim())->toBe('Azteca sp.')
        ->and($especimen->localidadVerbatim())->toBe('Yasuní, Onkonegare')
        ->and($especimen->fechaVerbatim())->toBe('21-Jul-01')
        ->and($especimen->fechaColectaFin())->toBe('2001-07-25')
        ->and($especimen->individualCountVerbatim())->toBe('RBSO9-2')
        ->and($especimen->sex())->toBe('female')
        ->and($especimen->typeStatus())->toBe('paratype')
        ->and($especimen->coordVerbatim())->toBe('dañada')
        ->and($especimen->elevationMaxM())->toBe(250.0)
        ->and($especimen->endemic())->toBeTrue()
        ->and($especimen->dnaNotes())->toBe('CO1 secuenciado')
        ->and($especimen->actaRecepcion())->toBe('AR-2024-001');
});

test('marcarParaRevision cambia estado y motivo', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
    );

    $especimen->marcarParaRevision('discrepancia en localidad enlazada');

    expect($especimen->estadoRevision()->value)->toBe('pendiente')
        ->and($especimen->motivoRevision())->toBe('discrepancia en localidad enlazada');
});

test('marcarParaRevision con motivo vacío lanza excepción', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
    );

    $especimen->marcarParaRevision('   ');
})->throws(InvalidArgumentException::class, 'motivo');

test('confirmarRevision transiciona pendiente → confirmada limpiando motivo', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
        motivoRevision: 'a verificar',
    );

    $especimen->confirmarRevision();

    expect($especimen->estadoRevision()->value)->toBe('confirmada')
        ->and($especimen->motivoRevision())->toBeNull();
});

test('confirmarRevision dos veces lanza DomainException', function (): void {
    $especimen = Especimen::crear(
        EspecimenId::generar(),
        'MEPN-INV-1',
        'taxon-uuid-dummy-0000-000000000001',
        'X',
        '2024-01-01',
        'C',
    );
    $especimen->confirmarRevision();

    $especimen->confirmarRevision();
})->throws(DomainException::class);
