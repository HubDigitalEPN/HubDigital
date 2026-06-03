<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverDuplicadoDeCatalogNumber\ResolverDuplicadoDeCatalogNumberHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ResolverDuplicadoDeCatalogNumber\ResolverDuplicadoDeCatalogNumberInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;

function sembrarGrupoConCatalogNumber(string $catalogNumber, int $cantidad): InMemoryEspecimenRepository
{
    $repo = new InMemoryEspecimenRepository;
    for ($i = 0; $i < $cantidad; $i++) {
        $repo->guardar(Especimen::crear(
            EspecimenId::generar(),
            'COD-'.bin2hex(random_bytes(4)),
            null,
            'X',
            '2024-0'.($i + 1).'-01',
            'Colector',
            catalogNumber: $catalogNumber,
        ));
    }

    return $repo;
}

test('decisión eventos_distintos confirma todos los especímenes del grupo', function (): void {
    $repo = sembrarGrupoConCatalogNumber('1', 3);

    $output = (new ResolverDuplicadoDeCatalogNumberHandler($repo))->handle(
        new ResolverDuplicadoDeCatalogNumberInput(
            catalogNumber: '1',
            decision: ResolverDuplicadoDeCatalogNumberInput::DECISION_EVENTOS_DISTINTOS,
            motivo: 'confirmados como recolecciones independientes en años distintos',
        )
    );

    expect($output->especimenesAfectados)->toBe(3);

    foreach ($repo->buscarTodos() as $e) {
        expect($e->estadoRevision()->value)->toBe('confirmada');
    }
});

test('decisión error_catalogacion marca para revisión con motivo', function (): void {
    $repo = sembrarGrupoConCatalogNumber('1', 2);

    $output = (new ResolverDuplicadoDeCatalogNumberHandler($repo))->handle(
        new ResolverDuplicadoDeCatalogNumberInput(
            catalogNumber: '1',
            decision: ResolverDuplicadoDeCatalogNumberInput::DECISION_ERROR_CATALOGACION,
            motivo: 'detectada inconsistencia, requiere renumeración',
        )
    );

    expect($output->especimenesAfectados)->toBe(2);

    foreach ($repo->buscarTodos() as $e) {
        expect($e->estadoRevision()->value)->toBe('pendiente')
            ->and($e->motivoRevision())->toBe('detectada inconsistencia, requiere renumeración');
    }
});

test('rechaza decisión inválida', function (): void {
    $repo = sembrarGrupoConCatalogNumber('1', 2);

    (new ResolverDuplicadoDeCatalogNumberHandler($repo))->handle(
        new ResolverDuplicadoDeCatalogNumberInput(
            catalogNumber: '1',
            decision: 'almacenarlo',
            motivo: 'X',
        )
    );
})->throws(InvalidArgumentException::class, 'Decisión');

test('rechaza catalogNumber vacío', function (): void {
    $repo = new InMemoryEspecimenRepository;

    (new ResolverDuplicadoDeCatalogNumberHandler($repo))->handle(
        new ResolverDuplicadoDeCatalogNumberInput(
            catalogNumber: '   ',
            decision: ResolverDuplicadoDeCatalogNumberInput::DECISION_EVENTOS_DISTINTOS,
            motivo: 'X',
        )
    );
})->throws(InvalidArgumentException::class, 'catalogNumber');

test('rechaza motivo vacío', function (): void {
    $repo = sembrarGrupoConCatalogNumber('1', 2);

    (new ResolverDuplicadoDeCatalogNumberHandler($repo))->handle(
        new ResolverDuplicadoDeCatalogNumberInput(
            catalogNumber: '1',
            decision: ResolverDuplicadoDeCatalogNumberInput::DECISION_EVENTOS_DISTINTOS,
            motivo: '',
        )
    );
})->throws(InvalidArgumentException::class, 'motivo');

test('solo afecta a especímenes con el catalog_number indicado', function (): void {
    $repo = new InMemoryEspecimenRepository;
    $repo->guardar(Especimen::crear(EspecimenId::generar(), 'A', null, 'X', '2024-01-01', 'C', catalogNumber: '1'));
    $repo->guardar(Especimen::crear(EspecimenId::generar(), 'B', null, 'X', '2024-01-01', 'C', catalogNumber: '2'));

    $output = (new ResolverDuplicadoDeCatalogNumberHandler($repo))->handle(
        new ResolverDuplicadoDeCatalogNumberInput(
            catalogNumber: '1',
            decision: ResolverDuplicadoDeCatalogNumberInput::DECISION_EVENTOS_DISTINTOS,
            motivo: 'X',
        )
    );

    expect($output->especimenesAfectados)->toBe(1);
});
