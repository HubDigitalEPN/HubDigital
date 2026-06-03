<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoPermiso;

test('valoresAceptados contiene los tres tipos esperados', function (): void {
    expect(TipoPermiso::valoresAceptados())->toBe(['research', 'transport', 'export_import']);
});

test('valor válido del enum se construye con from()', function (string $valor): void {
    expect(TipoPermiso::from($valor)->value)->toBe($valor);
})->with(['research', 'transport', 'export_import']);

test('desdeValorFlexible acepta alias en español', function (string $alias, TipoPermiso $esperado): void {
    expect(TipoPermiso::desdeValorFlexible($alias))->toBe($esperado);
})->with([
    ['investigacion', TipoPermiso::Research],
    ['investigación', TipoPermiso::Research],
    ['transporte', TipoPermiso::Transport],
    ['export/import', TipoPermiso::ExportImport],
    ['exportacion_importacion', TipoPermiso::ExportImport],
]);

test('desdeValorFlexible devuelve null para valores desconocidos', function (): void {
    expect(TipoPermiso::desdeValorFlexible('almacenamiento'))->toBeNull();
});

test('equals compara identidad de enum', function (): void {
    expect(TipoPermiso::Research->equals(TipoPermiso::Research))->toBeTrue()
        ->and(TipoPermiso::Research->equals(TipoPermiso::Transport))->toBeFalse();
});
