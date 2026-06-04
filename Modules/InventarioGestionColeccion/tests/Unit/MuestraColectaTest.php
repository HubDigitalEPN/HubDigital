<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\MuestraColecta;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\MuestraColectaId;

test('crear una muestra vacía la deja en estado Pendiente', function (): void {
    $muestra = MuestraColecta::crear(MuestraColectaId::generar());

    expect($muestra->estadoRevision())->toBe(EstadoRevision::Pendiente)
        ->and($muestra->codigoMuestra())->toBeNull()
        ->and($muestra->localidadId())->toBeNull()
        ->and($muestra->motivoRevision())->toBeNull();
});

test('crear con datos los persiste y normaliza', function (): void {
    $muestra = MuestraColecta::crear(
        id: MuestraColectaId::generar(),
        codigoMuestra: '  BT2F3  ',
        fechaColecta: new DateTimeImmutable('2001-07-21'),
        fechaVerbatim: '21-Jul-01',
        localidadVerbatim: 'Yasuní, Onkonegare',
        colector: 'Juan Pérez',
        samplingProtocol: 'Trampa de Berlese',
        motivoRevision: 'agrupación por oldCode sin confirmar',
    );

    expect($muestra->codigoMuestra())->toBe('BT2F3')
        ->and($muestra->fechaColecta()?->format('Y-m-d'))->toBe('2001-07-21')
        ->and($muestra->fechaVerbatim())->toBe('21-Jul-01')
        ->and($muestra->localidadVerbatim())->toBe('Yasuní, Onkonegare')
        ->and($muestra->colector())->toBe('Juan Pérez')
        ->and($muestra->motivoRevision())->toBe('agrupación por oldCode sin confirmar');
});

test('crear acepta localidadId válido y lo enlaza', function (): void {
    $localidadId = LocalidadId::generar();
    $muestra = MuestraColecta::crear(
        id: MuestraColectaId::generar(),
        localidadId: (string) $localidadId,
    );

    expect((string) $muestra->localidadId())->toBe((string) $localidadId);
});

test('confirmar transiciona Pendiente → Confirmada y limpia motivo', function (): void {
    $muestra = MuestraColecta::crear(
        id: MuestraColectaId::generar(),
        motivoRevision: 'pendiente de verificar',
    );

    $muestra->confirmar();

    expect($muestra->estadoRevision())->toBe(EstadoRevision::Confirmada)
        ->and($muestra->motivoRevision())->toBeNull();
});

test('confirmar una muestra ya Confirmada lanza DomainException', function (): void {
    $muestra = MuestraColecta::crear(MuestraColectaId::generar());
    $muestra->confirmar();

    $muestra->confirmar();
})->throws(DomainException::class, 'confirmar');

test('marcarParaRevision resetea a Pendiente con motivo', function (): void {
    $muestra = MuestraColecta::crear(MuestraColectaId::generar());
    $muestra->confirmar();

    $muestra->marcarParaRevision('detectada inconsistencia con localidad');

    expect($muestra->estadoRevision())->toBe(EstadoRevision::Pendiente)
        ->and($muestra->motivoRevision())->toBe('detectada inconsistencia con localidad');
});

test('marcarParaRevision con motivo vacío lanza InvalidArgumentException', function (): void {
    $muestra = MuestraColecta::crear(MuestraColectaId::generar());

    $muestra->marcarParaRevision('   ');
})->throws(InvalidArgumentException::class, 'motivo');

test('enlazarLocalidad asigna localidadId', function (): void {
    $muestra = MuestraColecta::crear(MuestraColectaId::generar());
    $localidadId = LocalidadId::generar();

    $muestra->enlazarLocalidad($localidadId);

    expect((string) $muestra->localidadId())->toBe((string) $localidadId);
});
