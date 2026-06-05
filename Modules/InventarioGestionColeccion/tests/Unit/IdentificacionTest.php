<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Identificacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\IdentificacionId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

test('crear una identificación la deja como actual por defecto', function (): void {
    $i = Identificacion::crear(
        id: IdentificacionId::generar(),
        especimenId: EspecimenId::generar(),
    );

    expect($i->esActual())->toBeTrue()
        ->and($i->taxonId())->toBeNull()
        ->and($i->identificadoPor())->toBeNull();
});

test('crear con todos los datos los almacena', function (): void {
    $taxonId = TaxonId::generar();
    $i = Identificacion::crear(
        id: IdentificacionId::generar(),
        especimenId: EspecimenId::generar(),
        taxonId: (string) $taxonId,
        identificadoPor: 'Dra. María Cárdenas',
        fechaDeterminacion: new DateTimeImmutable('2024-05-12'),
        calificador: 'cf.',
        observaciones: 'Determinación tentativa, falta material genético',
    );

    expect((string) $i->taxonId())->toBe((string) $taxonId)
        ->and($i->identificadoPor())->toBe('Dra. María Cárdenas')
        ->and($i->fechaDeterminacion()?->format('Y-m-d'))->toBe('2024-05-12')
        ->and($i->calificador())->toBe('cf.')
        ->and($i->observaciones())->toBe('Determinación tentativa, falta material genético');
});

test('crear con campos opcionales vacíos los normaliza a null', function (?string $valor): void {
    $i = Identificacion::crear(
        id: IdentificacionId::generar(),
        especimenId: EspecimenId::generar(),
        identificadoPor: $valor,
        calificador: $valor,
        observaciones: $valor,
    );

    expect($i->identificadoPor())->toBeNull()
        ->and($i->calificador())->toBeNull()
        ->and($i->observaciones())->toBeNull();
})->with([null, '', '   ']);

test('marcarComoSuperseded baja el flag es_actual', function (): void {
    $i = Identificacion::crear(IdentificacionId::generar(), EspecimenId::generar());

    $i->marcarComoSuperseded();

    expect($i->esActual())->toBeFalse();
});

test('marcarComoActual restaura el flag', function (): void {
    $i = Identificacion::crear(IdentificacionId::generar(), EspecimenId::generar(), esActual: false);

    $i->marcarComoActual();

    expect($i->esActual())->toBeTrue();
});
