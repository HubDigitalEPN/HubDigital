<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfigurarDataset\ConfigurarDatasetHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfigurarDataset\ConfigurarDatasetInput;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryDatasetConfigRepository;

test('cuando no hay configuración existente, crea una nueva con creado=true', function (): void {
    $repo = new InMemoryDatasetConfigRepository;
    $handler = new ConfigurarDatasetHandler($repo);

    $output = $handler->handle(new ConfigurarDatasetInput(
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        datasetName: 'Catálogo MEPN Invertebrados',
    ));

    expect($output->creado)->toBeTrue()
        ->and($output->institutionCode)->toBe('MEPN')
        ->and($output->collectionCode)->toBe('INV')
        ->and($output->datasetName)->toBe('Catálogo MEPN Invertebrados')
        ->and($output->basisOfRecord)->toBe('PreservedSpecimen');
});

test('cuando existe una configuración previa, actualiza esa misma instancia y creado=false', function (): void {
    $repo = new InMemoryDatasetConfigRepository;
    $handler = new ConfigurarDatasetHandler($repo);

    $primera = $handler->handle(new ConfigurarDatasetInput(
        institutionCode: 'MEPN',
        collectionCode: 'INV',
    ));

    $segunda = $handler->handle(new ConfigurarDatasetInput(
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        datasetName: 'Nuevo nombre',
        license: 'CC0 1.0',
    ));

    expect($segunda->creado)->toBeFalse()
        ->and((string) $segunda->id)->toBe((string) $primera->id)
        ->and($segunda->datasetName)->toBe('Nuevo nombre')
        ->and($segunda->license)->toBe('CC0 1.0');
});

test('persiste correctamente todos los campos provistos', function (): void {
    $repo = new InMemoryDatasetConfigRepository;
    $handler = new ConfigurarDatasetHandler($repo);

    $output = $handler->handle(new ConfigurarDatasetInput(
        institutionCode: 'MEPN',
        collectionCode: 'INV',
        basisOfRecord: 'PreservedSpecimen',
        institutionId: 'https://www.epn.edu.ec',
        collectionId: 'https://www.epn.edu.ec/collections/mepn',
        datasetName: 'MEPN Invertebrados',
        ownerInstitutionCode: 'EPN',
        rightsHolder: 'Escuela Politécnica Nacional',
        accessRights: 'open',
        license: 'CC0 1.0',
        informationWithheld: 'coordinates withheld for endangered species',
        emlContacto: 'curador@epn.edu.ec',
        emlTitulo: 'Catálogo Invertebrados MEPN',
    ));

    $persistido = $repo->obtenerActual();

    expect($persistido)->not->toBeNull()
        ->and($persistido->institutionId())->toBe('https://www.epn.edu.ec')
        ->and($persistido->rightsHolder())->toBe('Escuela Politécnica Nacional')
        ->and($persistido->emlTitulo())->toBe('Catálogo Invertebrados MEPN')
        ->and($output->emlContacto)->toBe('curador@epn.edu.ec');
});

test('al actualizar, conserva el mismo id', function (): void {
    $repo = new InMemoryDatasetConfigRepository;
    $handler = new ConfigurarDatasetHandler($repo);

    $primera = $handler->handle(new ConfigurarDatasetInput(
        institutionCode: 'MEPN',
        collectionCode: 'INV',
    ));

    $handler->handle(new ConfigurarDatasetInput(
        institutionCode: 'MEPN',
        collectionCode: 'INV',
    ));

    $actual = $repo->obtenerActual();

    expect((string) $actual->id())->toBe((string) $primera->id);
});
