<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\DatasetConfig;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\DatasetConfigRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\BasisOfRecord;
use Modules\InventarioGestionColeccion\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('guardar y recuperar por id persiste todos los campos', function (): void {
    $repo = app(DatasetConfigRepositoryInterface::class);

    $config = DatasetConfig::crear(
        id: $repo->nextIdentity(),
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        basisOfRecord: 'PreservedSpecimen',
        institutionId: 'https://www.epn.edu.ec',
        datasetName: 'MEPN Invertebrados',
        license: 'CC0 1.0',
        emlContacto: 'curador@epn.edu.ec',
    );

    $repo->guardar($config);

    $encontrado = $repo->buscarPorId($config->id());

    expect($encontrado)->not->toBeNull()
        ->and($encontrado->institutionCode()->value)->toBe('MEPN')
        ->and($encontrado->collectionCode()->value)->toBe('INV')
        ->and($encontrado->basisOfRecord())->toBe(BasisOfRecord::PreservedSpecimen)
        ->and($encontrado->institutionId())->toBe('https://www.epn.edu.ec')
        ->and($encontrado->datasetName())->toBe('MEPN Invertebrados')
        ->and($encontrado->license()?->value)->toBe('CC0 1.0')
        ->and($encontrado->emlContacto())->toBe('curador@epn.edu.ec');
});

test('obtenerActual devuelve null cuando no hay registros', function (): void {
    $repo = app(DatasetConfigRepositoryInterface::class);

    expect($repo->obtenerActual())->toBeNull();
});

test('obtenerActual devuelve la configuración más reciente', function (): void {
    $repo = app(DatasetConfigRepositoryInterface::class);

    $primera = DatasetConfig::crear(
        id: $repo->nextIdentity(),
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        datasetName: 'Primera',
    );
    $repo->guardar($primera);

    // Forzar un timestamp posterior
    sleep(1);

    $segunda = DatasetConfig::crear(
        id: $repo->nextIdentity(),
        institutionCode: 'MEPN',
        collectionCode: 'BOT',
        datasetName: 'Segunda',
    );
    $repo->guardar($segunda);

    $actual = $repo->obtenerActual();

    expect($actual)->not->toBeNull()
        ->and($actual->datasetName())->toBe('Segunda');
});

test('nextIdentity genera identificadores únicos', function (): void {
    $repo = app(DatasetConfigRepositoryInterface::class);

    $id1 = $repo->nextIdentity();
    $id2 = $repo->nextIdentity();

    expect((string) $id1)->not->toBe((string) $id2);
});

test('guardar actualiza un registro existente sin duplicar', function (): void {
    $repo = app(DatasetConfigRepositoryInterface::class);

    $config = DatasetConfig::crear(
        id: $repo->nextIdentity(),
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        datasetName: 'Inicial',
    );
    $repo->guardar($config);

    $config->actualizar(
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        datasetName: 'Actualizado',
    );
    $repo->guardar($config);

    $encontrado = $repo->buscarPorId($config->id());

    expect($encontrado->datasetName())->toBe('Actualizado');
});
