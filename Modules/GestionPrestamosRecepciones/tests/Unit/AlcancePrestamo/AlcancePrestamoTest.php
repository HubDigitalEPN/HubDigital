<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;

test('crea Nacional correctamente', function (): void {
    $alcance = AlcancePrestamo::Nacional;

    expect($alcance->value)->toBe('nacional');
    expect($alcance->esInternacional())->toBeFalse();
});

test('crea Internacional correctamente', function (): void {
    $alcance = AlcancePrestamo::Internacional;

    expect($alcance->value)->toBe('internacional');
    expect($alcance->esInternacional())->toBeTrue();
});

test('from string válido retorna la instancia correcta', function (): void {
    expect(AlcancePrestamo::from('nacional'))->toBe(AlcancePrestamo::Nacional);
    expect(AlcancePrestamo::from('internacional'))->toBe(AlcancePrestamo::Internacional);
});

test('from string inválido lanza excepción', function (): void {
    expect(fn () => AlcancePrestamo::from('extranjero'))->toThrow(ValueError::class);
});

test('equals retorna true para el mismo caso', function (): void {
    expect(AlcancePrestamo::Nacional->equals(AlcancePrestamo::Nacional))->toBeTrue();
    expect(AlcancePrestamo::Internacional->equals(AlcancePrestamo::Internacional))->toBeTrue();
});

test('equals retorna false para casos distintos', function (): void {
    expect(AlcancePrestamo::Nacional->equals(AlcancePrestamo::Internacional))->toBeFalse();
});
