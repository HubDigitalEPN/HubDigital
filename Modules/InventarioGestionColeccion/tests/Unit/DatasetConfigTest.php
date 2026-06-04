<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DatasetConfig;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\BasisOfRecord;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\DatasetConfigId;

test('crear con campos requeridos asigna PreservedSpecimen como basis por defecto', function (): void {
    $config = DatasetConfig::crear(
        id: DatasetConfigId::generar(),
        institutionCode: 'MEPN',
        collectionCode: 'INV',
    );

    expect($config->institutionCode()->value)->toBe('MEPN')
        ->and($config->collectionCode()->value)->toBe('INV')
        ->and($config->basisOfRecord())->toBe(BasisOfRecord::PreservedSpecimen)
        ->and($config->license())->toBeNull()
        ->and($config->datasetName())->toBeNull();
});

test('crear con basis explícito lo respeta', function (): void {
    $config = DatasetConfig::crear(
        id: DatasetConfigId::generar(),
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        basisOfRecord: 'MaterialSample',
    );

    expect($config->basisOfRecord())->toBe(BasisOfRecord::MaterialSample);
});

test('crear recorta espacios en institutionCode y collectionCode', function (): void {
    $config = DatasetConfig::crear(
        id: DatasetConfigId::generar(),
        institutionCode: '  MEPN  ',
        collectionCode: '  INV  ',
    );

    expect($config->institutionCode()->value)->toBe('MEPN')
        ->and($config->collectionCode()->value)->toBe('INV');
});

test('crear con institutionCode vacío lanza excepción', function (): void {
    DatasetConfig::crear(
        id: DatasetConfigId::generar(),
        institutionCode: '   ',
        collectionCode: 'INV',
    );
})->throws(InvalidArgumentException::class, 'InstitutionCode');

test('crear con collectionCode vacío lanza excepción', function (): void {
    DatasetConfig::crear(
        id: DatasetConfigId::generar(),
        institutionCode: 'MEPN',
        collectionCode: '   ',
    );
})->throws(InvalidArgumentException::class, 'CollectionCode');

test('crear con license vacío deja license null', function (): void {
    $config = DatasetConfig::crear(
        id: DatasetConfigId::generar(),
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        license: '   ',
    );

    expect($config->license())->toBeNull();
});

test('crear con license válida lo guarda', function (): void {
    $config = DatasetConfig::crear(
        id: DatasetConfigId::generar(),
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        license: 'CC0 1.0',
    );

    expect($config->license()?->value)->toBe('CC0 1.0');
});

test('actualizar cambia los campos modificables', function (): void {
    $config = DatasetConfig::crear(
        id: DatasetConfigId::generar(),
        institutionCode: 'MEPN',
        collectionCode: 'INV',
    );

    $config->actualizar(
        institutionCode: 'EPN',
        collectionCode: 'BOT',
        basisOfRecord: 'FossilSpecimen',
        datasetName: 'Catálogo Botánico EPN',
        license: 'CC-BY 4.0',
        emlContacto: 'curador@epn.edu.ec',
    );

    expect($config->institutionCode()->value)->toBe('EPN')
        ->and($config->collectionCode()->value)->toBe('BOT')
        ->and($config->basisOfRecord())->toBe(BasisOfRecord::FossilSpecimen)
        ->and($config->datasetName())->toBe('Catálogo Botánico EPN')
        ->and($config->license()?->value)->toBe('CC-BY 4.0')
        ->and($config->emlContacto())->toBe('curador@epn.edu.ec');
});

test('actualizar normaliza opcionales vacíos a null', function (): void {
    $config = DatasetConfig::crear(
        id: DatasetConfigId::generar(),
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        datasetName: 'Inicial',
    );

    $config->actualizar(
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        datasetName: '   ',
    );

    expect($config->datasetName())->toBeNull();
});

test('el id asignado en crear se conserva', function (): void {
    $id = DatasetConfigId::generar();
    $config = DatasetConfig::crear(id: $id, institutionCode: 'MEPN', collectionCode: 'INV');

    expect((string) $config->id())->toBe((string) $id);
});
