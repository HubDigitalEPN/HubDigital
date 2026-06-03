<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Localidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\LocalidadRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\MuestraColectaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;
use Modules\InventarioGestionColeccion\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('guarda y recupera una muestra con campos completos', function (): void {
    $localidadRepo = app(LocalidadRepositoryInterface::class);
    $localidad = Localidad::crear($localidadRepo->nextIdentity(), 'Yasuní', 'area_protegida');
    $localidadRepo->guardar($localidad);

    $repo = app(MuestraColectaRepositoryInterface::class);
    $muestra = MuestraColecta::crear(
        id: $repo->nextIdentity(),
        codigoMuestra: 'BT2F3',
        fechaColecta: new DateTimeImmutable('2001-07-21'),
        fechaVerbatim: '21-Jul-01',
        localidadId: (string) $localidad->id(),
        localidadVerbatim: 'Yasuní, Onkonegare',
        colector: 'Juan Pérez',
        samplingProtocol: 'Trampa de Berlese',
        motivoRevision: 'agrupación por oldCode sin confirmar',
    );

    $repo->guardar($muestra);

    $encontrada = $repo->buscarPorId($muestra->id());

    expect($encontrada)->not->toBeNull()
        ->and($encontrada->codigoMuestra())->toBe('BT2F3')
        ->and($encontrada->fechaColecta()?->format('Y-m-d'))->toBe('2001-07-21')
        ->and($encontrada->fechaVerbatim())->toBe('21-Jul-01')
        ->and((string) $encontrada->localidadId())->toBe((string) $localidad->id())
        ->and($encontrada->localidadVerbatim())->toBe('Yasuní, Onkonegare')
        ->and($encontrada->estadoRevision())->toBe(EstadoRevision::Pendiente);
});

test('guarda una muestra mínima sin localidad ni fechas', function (): void {
    $repo = app(MuestraColectaRepositoryInterface::class);
    $muestra = MuestraColecta::crear($repo->nextIdentity());
    $repo->guardar($muestra);

    $encontrada = $repo->buscarPorId($muestra->id());

    expect($encontrada)->not->toBeNull()
        ->and($encontrada->localidadId())->toBeNull()
        ->and($encontrada->fechaColecta())->toBeNull();
});

test('persiste transición de estado tras confirmar', function (): void {
    $repo = app(MuestraColectaRepositoryInterface::class);
    $muestra = MuestraColecta::crear($repo->nextIdentity(), motivoRevision: 'X');
    $repo->guardar($muestra);

    $muestra->confirmar();
    $repo->guardar($muestra);

    $encontrada = $repo->buscarPorId($muestra->id());

    expect($encontrada->estadoRevision())->toBe(EstadoRevision::Confirmada)
        ->and($encontrada->motivoRevision())->toBeNull();
});

test('buscarPorCodigoMuestra encuentra la muestra correcta', function (): void {
    $repo = app(MuestraColectaRepositoryInterface::class);
    $m1 = MuestraColecta::crear($repo->nextIdentity(), codigoMuestra: 'BT2F3');
    $m2 = MuestraColecta::crear($repo->nextIdentity(), codigoMuestra: 'LT2F2');
    $repo->guardar($m1);
    $repo->guardar($m2);

    $resultado = $repo->buscarPorCodigoMuestra('LT2F2');

    expect($resultado)->not->toBeNull()
        ->and((string) $resultado->id())->toBe((string) $m2->id());
});

test('buscarPorEstado filtra por estado_revision', function (): void {
    $repo = app(MuestraColectaRepositoryInterface::class);
    $m1 = MuestraColecta::crear($repo->nextIdentity());
    $m2 = MuestraColecta::crear($repo->nextIdentity());
    $m2->confirmar();
    $repo->guardar($m1);
    $repo->guardar($m2);

    $pendientes = $repo->buscarPorEstado(EstadoRevision::Pendiente);
    $confirmadas = $repo->buscarPorEstado(EstadoRevision::Confirmada);

    expect($pendientes)->toHaveCount(1)
        ->and($confirmadas)->toHaveCount(1);
});
