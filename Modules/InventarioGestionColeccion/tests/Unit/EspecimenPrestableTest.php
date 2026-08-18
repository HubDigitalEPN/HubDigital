<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito\IngresarLoteDepositoHandler;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\EspecimenPrestable;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCustodia;

/**
 * El régimen de custodia manda sobre la disponibilidad.
 *
 * Estas pruebas fijan el fallo que motivó el servicio: material devuelto a su
 * depositante seguía apareciendo como prestable porque el filtro solo miraba `estado`.
 */
function especimenConCustodia(?EstadoCustodia $custodia, string $codigo = 'MEPN-INV-DEP-00002-0001'): Especimen
{
    return Especimen::crear(
        id: EspecimenId::generar(),
        codigoCatalogo: $codigo,
        taxonId: null,
        localidad: 'Yasuní',
        fechaColecta: '2023-02-12',
        colector: 'Padilla, D.',
        individualCount: 3,
        estadoCustodia: $custodia,
    );
}

it('no presta material devuelto a su depositante', function (): void {
    $especimen = especimenConCustodia(EstadoCustodia::Devuelto);

    expect((new EspecimenPrestable)->puedePrestarse($especimen))->toBeFalse()
        ->and((new EspecimenPrestable)->motivoNoPrestable($especimen))
        ->toContain('devuelto a su depositante');
});

it('no presta material en cuarentena', function (): void {
    $especimen = especimenConCustodia(EstadoCustodia::Cuarentena);

    expect((new EspecimenPrestable)->puedePrestarse($especimen))->toBeFalse()
        ->and((new EspecimenPrestable)->motivoNoPrestable($especimen))
        ->toContain('cuarentena');
});

it('sí presta material en depósito temporal, que sigue bajo custodia del museo', function (): void {
    $especimen = especimenConCustodia(EstadoCustodia::Temporal);

    expect((new EspecimenPrestable)->puedePrestarse($especimen))->toBeTrue()
        ->and((new EspecimenPrestable)->motivoNoPrestable($especimen))->toBeNull();
});

it('sí presta el material heredado del catálogo, que no declara régimen', function (): void {
    $especimen = especimenConCustodia(null, 'MEPN-INV-000123');

    expect((new EspecimenPrestable)->puedePrestarse($especimen))->toBeTrue();
});

it('impide marcar en préstamo material que ya salió de la colección', function (): void {
    $especimen = especimenConCustodia(EstadoCustodia::Devuelto);

    expect(fn () => $especimen->marcarEnPrestamo())
        ->toThrow(DomainException::class, 'ya no está en la colección');
});

it('impide marcar en préstamo material en cuarentena', function (): void {
    $especimen = especimenConCustodia(EstadoCustodia::Cuarentena);

    expect(fn () => $especimen->marcarEnPrestamo())
        ->toThrow(DomainException::class, 'cuarentena');
});

it('deja prestar material bajo custodia temporal', function (): void {
    $especimen = especimenConCustodia(EstadoCustodia::Temporal);
    $especimen->marcarEnPrestamo();

    expect($especimen->estado()->value)->toBe('en_prestamo');
});

it('traduce los tres regímenes de ingreso que emite el módulo de recepciones', function (string $entrada, EstadoCustodia $esperado): void {
    expect(IngresarLoteDepositoHandler::custodiaDesdeIngreso($entrada))->toBe($esperado);
})->with([
    ['Temporal', EstadoCustodia::Temporal],
    ['Permanente', EstadoCustodia::Permanente],
    ['Cuarentena', EstadoCustodia::Cuarentena],
]);

it('rechaza con un mensaje legible un régimen de ingreso desconocido', function (): void {
    expect(fn () => IngresarLoteDepositoHandler::custodiaDesdeIngreso('Devuelto'))
        ->toThrow(InvalidArgumentException::class, 'Régimen de custodia "Devuelto" desconocido');
});
