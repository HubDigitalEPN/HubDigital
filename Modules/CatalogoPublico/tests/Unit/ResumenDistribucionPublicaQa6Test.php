<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Application\Services\ResumenDistribucionPublica;

function taxonesDistribucionQa6(): array
{
    return [
        'a' => (object) ['rango' => 'especie', 'nombre_cientifico' => 'Camponotus femoratus'],
        'b' => (object) ['rango' => 'especie', 'nombre_cientifico' => 'Camponotus femoratus'],
        'c' => (object) ['rango' => 'especie', 'nombre_cientifico' => 'Atta distinta'],
        'g' => (object) ['rango' => 'genero', 'nombre_cientifico' => 'Atta'],
        'p' => (object) ['rango' => 'especie', 'nombre_cientifico' => 'Atta pendiente de revisión'],
        'i' => (object) ['rango' => 'especie', 'nombre_cientifico' => 'Atta linaje inválido'],
    ];
}

test('QA6 provincia suma todos los registros públicos sin contar identificaciones inválidas como especies', function (): void {
    $filas = [
        (object) ['taxon_id' => 'a', 'provincia_clave' => 'narino', 'provincia' => 'Narino', 'registros' => '2'],
        (object) ['taxon_id' => 'b', 'provincia_clave' => 'narino', 'provincia' => 'Nariño', 'registros' => '3'],
        (object) ['taxon_id' => 'c', 'provincia_clave' => 'narino', 'provincia' => 'NARIÑO', 'registros' => '1'],
        (object) ['taxon_id' => 'c', 'provincia_clave' => 'choco', 'provincia' => 'Choco', 'registros' => '4'],
        (object) ['taxon_id' => 'a', 'provincia_clave' => 'choco', 'provincia' => 'Chocó', 'registros' => '1'],
        (object) ['taxon_id' => 'g', 'provincia_clave' => 'narino', 'provincia' => 'Narino', 'registros' => '99'],
        (object) ['taxon_id' => 'p', 'provincia_clave' => 'narino', 'provincia' => 'Narino', 'registros' => '99'],
        (object) ['taxon_id' => 'i', 'provincia_clave' => 'narino', 'provincia' => 'Narino', 'registros' => '99'],
        (object) ['taxon_id' => null, 'provincia_clave' => 'narino', 'provincia' => 'Narino', 'registros' => '99'],
        (object) ['taxon_id' => 'a', 'provincia_clave' => 'pendiente de revision', 'provincia' => 'pendiente de revisión', 'registros' => '99'],
        (object) ['taxon_id' => 'a', 'provincia_clave' => "\u{200B}", 'provincia' => "\u{200B}", 'registros' => '99'],
    ];
    $resultado = ResumenDistribucionPublica::riqueza($filas, taxonesDistribucionQa6(), ['a', 'b', 'c', 'g', 'p']);
    expect($resultado)->toBe([
        ['provincia' => 'Nariño', 'especies' => 2, 'registros' => 402],
        ['provincia' => 'Chocó', 'especies' => 2, 'registros' => 5],
    ])->and(ResumenDistribucionPublica::riqueza(array_reverse($filas), taxonesDistribucionQa6(), ['a', 'b', 'c', 'g', 'p']))->toBe($resultado)
        ->and(ResumenDistribucionPublica::riqueza($filas, taxonesDistribucionQa6(), []))->toBe([
            ['provincia' => 'Nariño', 'especies' => 0, 'registros' => 402],
            ['provincia' => 'Chocó', 'especies' => 0, 'registros' => 5],
        ]);
});

test('QA6 décadas cuentan nombres distintos dentro de cada década y mantienen orden cronológico y totales', function (): void {
    $filas = [
        (object) ['taxon_id' => 'a', 'decada' => '2000', 'registros' => '2'],
        (object) ['taxon_id' => 'b', 'decada' => '2000', 'registros' => '3'],
        (object) ['taxon_id' => 'c', 'decada' => '2000', 'registros' => '1'],
        (object) ['taxon_id' => 'a', 'decada' => '1990', 'registros' => '4'],
        (object) ['taxon_id' => 'c', 'decada' => '1990', 'registros' => '1'],
        (object) ['taxon_id' => 'g', 'decada' => '1980', 'registros' => '99'],
        (object) ['taxon_id' => 'p', 'decada' => '1980', 'registros' => '99'],
        (object) ['taxon_id' => 'i', 'decada' => '1980', 'registros' => '99'],
        (object) ['taxon_id' => null, 'decada' => '1980', 'registros' => '99'],
    ];
    $resultado = ResumenDistribucionPublica::decadas($filas, taxonesDistribucionQa6(), ['a', 'b', 'c', 'g', 'p']);
    expect($resultado)->toBe([
        ['decada' => 1980, 'especies' => 0, 'registros' => 396],
        ['decada' => 1990, 'especies' => 2, 'registros' => 5],
        ['decada' => 2000, 'especies' => 2, 'registros' => 6],
    ])->and(ResumenDistribucionPublica::decadas(array_reverse($filas), taxonesDistribucionQa6(), ['a', 'b', 'c', 'g', 'p']))->toBe($resultado)
        ->and(ResumenDistribucionPublica::decadas([], taxonesDistribucionQa6(), ['a']))->toBe([]);
});

test('QA6 riqueza limita provincias después de completar la diversidad de cada grupo', function (): void {
    $taxones = taxonesDistribucionQa6();
    $filas = [];
    foreach (range(1, 12) as $i) {
        $provincia = 'Provincia '.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $filas[] = (object) ['taxon_id' => 'a', 'provincia_clave' => strtolower($provincia), 'provincia' => $provincia, 'registros' => 1];
    }
    $filas[] = (object) ['taxon_id' => 'c', 'provincia_clave' => 'provincia 12', 'provincia' => 'Provincia 12', 'registros' => 1];
    $resultado = ResumenDistribucionPublica::riqueza($filas, $taxones, ['a', 'c']);
    expect($resultado)->toHaveCount(10)->and($resultado[0])->toBe(['provincia' => 'Provincia 12', 'especies' => 2, 'registros' => 2])
        ->and(array_column($resultado, 'provincia'))->not->toContain('Provincia 10', 'Provincia 11');
});
