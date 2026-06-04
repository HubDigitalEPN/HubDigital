<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;

test('contiene los cinco rangos esperados', function (): void {
    expect(RangoLocalidad::valoresAceptados())->toBe([
        'pais', 'provincia', 'area_protegida', 'sector', 'sitio',
    ]);
});

test('jerarquía estrictamente ascendente', function (): void {
    expect(RangoLocalidad::Pais->nivelJerarquico())->toBeLessThan(RangoLocalidad::Provincia->nivelJerarquico())
        ->and(RangoLocalidad::Provincia->nivelJerarquico())->toBeLessThan(RangoLocalidad::AreaProtegida->nivelJerarquico())
        ->and(RangoLocalidad::AreaProtegida->nivelJerarquico())->toBeLessThan(RangoLocalidad::Sector->nivelJerarquico())
        ->and(RangoLocalidad::Sector->nivelJerarquico())->toBeLessThan(RangoLocalidad::Sitio->nivelJerarquico());
});

test('puedeSerPadreDe sólo de rangos descendentes', function (): void {
    expect(RangoLocalidad::Pais->puedeSerPadreDe(RangoLocalidad::Provincia))->toBeTrue()
        ->and(RangoLocalidad::Provincia->puedeSerPadreDe(RangoLocalidad::Sitio))->toBeTrue()
        ->and(RangoLocalidad::Sitio->puedeSerPadreDe(RangoLocalidad::Pais))->toBeFalse()
        ->and(RangoLocalidad::Pais->puedeSerPadreDe(RangoLocalidad::Pais))->toBeFalse();
});

test('desdeValorFlexible acepta alias en inglés y español', function (string $alias, RangoLocalidad $esperado): void {
    expect(RangoLocalidad::desdeValorFlexible($alias))->toBe($esperado);
})->with([
    ['país', RangoLocalidad::Pais],
    ['country', RangoLocalidad::Pais],
    ['state_province', RangoLocalidad::Provincia],
    ['stateprovince', RangoLocalidad::Provincia],
    ['area protegida', RangoLocalidad::AreaProtegida],
    ['protected_area', RangoLocalidad::AreaProtegida],
    ['site', RangoLocalidad::Sitio],
    ['locality', RangoLocalidad::Sitio],
]);

test('desdeValorFlexible devuelve null para valores desconocidos', function (): void {
    expect(RangoLocalidad::desdeValorFlexible('continente'))->toBeNull();
});
