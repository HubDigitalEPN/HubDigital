<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;

test('porDefecto retorna Pendiente', function (): void {
    expect(EstadoRevision::porDefecto())->toBe(EstadoRevision::Pendiente);
});

test('valores válidos del enum', function (string $valor): void {
    expect(EstadoRevision::from($valor)->value)->toBe($valor);
})->with(['pendiente', 'confirmada', 'descartada']);

test('valor inválido lanza ValueError', function (): void {
    EstadoRevision::from('en_revision');
})->throws(ValueError::class);

test('puedeConfirmarse sólo desde estado Pendiente', function (): void {
    expect(EstadoRevision::Pendiente->puedeConfirmarse())->toBeTrue()
        ->and(EstadoRevision::Confirmada->puedeConfirmarse())->toBeFalse()
        ->and(EstadoRevision::Descartada->puedeConfirmarse())->toBeFalse();
});

test('predicados de estado reflejan el valor actual', function (): void {
    expect(EstadoRevision::Confirmada->esConfirmada())->toBeTrue()
        ->and(EstadoRevision::Pendiente->esConfirmada())->toBeFalse()
        ->and(EstadoRevision::Descartada->esDescartada())->toBeTrue()
        ->and(EstadoRevision::Pendiente->esDescartada())->toBeFalse();
});
