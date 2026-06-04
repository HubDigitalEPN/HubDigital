<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\BasisOfRecord;

test('porDefecto devuelve PreservedSpecimen', function (): void {
    expect(BasisOfRecord::porDefecto())->toBe(BasisOfRecord::PreservedSpecimen);
});

test('cubre los valores Darwin Core esperados', function (string $valor): void {
    expect(BasisOfRecord::from($valor)->value)->toBe($valor);
})->with([
    'PreservedSpecimen',
    'FossilSpecimen',
    'LivingSpecimen',
    'MaterialSample',
    'HumanObservation',
    'MachineObservation',
    'Occurrence',
]);

test('un valor inválido lanza ValueError', function (): void {
    BasisOfRecord::from('NoExiste');
})->throws(ValueError::class);

test('equals compara identidad de enum', function (): void {
    expect(BasisOfRecord::PreservedSpecimen->equals(BasisOfRecord::PreservedSpecimen))->toBeTrue()
        ->and(BasisOfRecord::PreservedSpecimen->equals(BasisOfRecord::FossilSpecimen))->toBeFalse();
});
