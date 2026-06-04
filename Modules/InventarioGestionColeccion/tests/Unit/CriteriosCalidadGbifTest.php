<?php

declare(strict_types=1);

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\CriteriosCalidadGbif;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoRevision;

function crearEspecimenParaGbif(
    ?string $taxonId = 'taxon-uuid-dummy-0000-000000000001',
    ?float $lat = -0.5,
    ?float $lon = -76.0,
): Especimen {
    return Especimen::crear(
        EspecimenId::generar(),
        'COD-'.bin2hex(random_bytes(4)),
        $taxonId,
        'X',
        '2024-01-01',
        'C',
        decimalLatitude: $lat,
        decimalLongitude: $lon,
    );
}

test('espécimen completo es publicable', function (): void {
    $resultado = (new CriteriosCalidadGbif)->evaluar(crearEspecimenParaGbif());

    expect($resultado->esPublicable)->toBeTrue()
        ->and($resultado->motivosExclusion)->toBe([]);
});

test('sin taxonId se excluye con motivo "sin taxón"', function (): void {
    $resultado = (new CriteriosCalidadGbif)->evaluar(crearEspecimenParaGbif(taxonId: null));

    expect($resultado->esPublicable)->toBeFalse()
        ->and($resultado->motivosExclusion)->toContain('sin taxón identificado');
});

test('sin coordenadas se excluye con motivo "sin coordenadas"', function (?float $lat, ?float $lon): void {
    $resultado = (new CriteriosCalidadGbif)->evaluar(crearEspecimenParaGbif(lat: $lat, lon: $lon));

    expect($resultado->esPublicable)->toBeFalse()
        ->and($resultado->motivosExclusion)->toContain('sin coordenadas geográficas');
})->with([
    [null, null],
    [-0.5, null],
    [null, -76.0],
]);

test('estado_revision descartada excluye independiente de otros campos', function (): void {
    $especimen = crearEspecimenParaGbif();
    // Necesitamos pasar a descartada — sólo hay marcarParaRevision (→ pendiente)
    // y confirmarRevision (→ confirmada). Descartada no tiene método aún.
    // Para el test, marcamos via reflection sólo para verificar la regla.
    // PHP 8.1+: las propiedades privadas ya son accesibles vía reflection sin setAccessible().
    $r = new ReflectionClass($especimen);
    $prop = $r->getProperty('estadoRevision');
    $prop->setValue($especimen, EstadoRevision::Descartada);

    $resultado = (new CriteriosCalidadGbif)->evaluar($especimen);

    expect($resultado->esPublicable)->toBeFalse()
        ->and($resultado->motivosExclusion)->toContain('descartado por el curador');
});

test('estado pendiente no excluye (default tras creación)', function (): void {
    $resultado = (new CriteriosCalidadGbif)->evaluar(crearEspecimenParaGbif());

    expect($resultado->esPublicable)->toBeTrue();
});

test('múltiples motivos se acumulan', function (): void {
    $resultado = (new CriteriosCalidadGbif)->evaluar(crearEspecimenParaGbif(taxonId: null, lat: null, lon: null));

    expect($resultado->motivosExclusion)->toContain('sin taxón identificado')
        ->and($resultado->motivosExclusion)->toContain('sin coordenadas geográficas');
});
