<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\LocalidadId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TaxonId;

test('los identificadores del catálogo conservan UUID v4 y v5 importados', function (string $clase): void {
    foreach (['7605c19f-ec8f-46b5-a544-c6b3b7431389', '5fa5c21c-ebff-5153-834a-baff8cf07d43'] as $valor) {
        expect((string) $clase::desde($valor))->toBe($valor)
            ->and($clase::desde($valor)->equals($clase::desde($valor)))->toBeTrue()
            ->and((string) $clase::desde(strtoupper($valor)))->toBe(strtoupper($valor));
    }
    expect((string) $clase::generar())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
})->with([TaxonId::class, LocalidadId::class]);

test('el soporte de UUID importados no acepta identificadores mal formados', function (string $clase): void {
    foreach (['', 'texto', '5fa5c21c-ebff-5153-034a-baff8cf07d43', '5fa5c21c-ebff-5153-834a-baff8cf07d4z',
        ' 5fa5c21c-ebff-5153-834a-baff8cf07d43', '5fa5c21c-ebff-0153-834a-baff8cf07d43'] as $valor) {
        expect(fn () => $clase::desde($valor))->toThrow(InvalidArgumentException::class);
    }
})->with([TaxonId::class, LocalidadId::class]);
