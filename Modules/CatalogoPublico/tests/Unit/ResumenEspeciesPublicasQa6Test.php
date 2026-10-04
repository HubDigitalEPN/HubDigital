<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Application\Services\ResumenEspeciesPublicas;

test('QA6 el agregado reutilizado conserva multiplicidad de nombres permisos y exclusiones curatoriales', function (): void {
    $taxones = [
        'a' => (object) ['rango' => 'especie', 'nombre_cientifico' => 'Camponotus femoratus'],
        'b' => (object) ['rango' => 'especie', 'nombre_cientifico' => 'Camponotus femoratus'],
        'c' => (object) ['rango' => 'especie', 'nombre_cientifico' => 'Atta rara'],
        'd' => (object) ['rango' => 'genero', 'nombre_cientifico' => 'Atta'],
        'e' => (object) ['rango' => 'especie', 'nombre_cientifico' => 'Atta pendiente de revisión'],
        'f' => (object) ['rango' => 'especie', 'nombre_cientifico' => 'Atta linaje inválido'],
    ];
    $filas = [
        (object) ['taxon_id' => 'a', 'family_visible' => true, 'genus_visible' => true, 'total' => '2'],
        (object) ['taxon_id' => 'a', 'family_visible' => false, 'genus_visible' => false, 'total' => '1'],
        (object) ['taxon_id' => 'b', 'family_visible' => true, 'genus_visible' => true, 'total' => '2'],
        (object) ['taxon_id' => 'c', 'family_visible' => false, 'genus_visible' => false, 'total' => '3'],
        (object) ['taxon_id' => 'd', 'total' => '99'],
        (object) ['taxon_id' => 'e', 'total' => '99'],
        (object) ['taxon_id' => 'f', 'total' => '99'],
        (object) ['taxon_id' => null, 'total' => '99'],
    ];
    $resumen = ResumenEspeciesPublicas::desde($filas, $taxones, ['a', 'b', 'c', 'd', 'e']);
    expect($resumen['especies'])->toBe([
        ['nombre' => 'Camponotus femoratus', 'total' => 5], ['nombre' => 'Atta rara', 'total' => 3],
    ])->and($resumen['raras'])->toBe([['nombre' => 'Atta rara', 'total' => 3]]);
});

test('QA6 el ranking mantiene límites y desempate estable después de sumar la selección completa', function (): void {
    $taxones = $filas = $validos = [];
    foreach (range(1, 25) as $i) {
        $id = 'taxon-'.$i;
        $taxones[$id] = (object) ['rango' => 'especie', 'nombre_cientifico' => 'Qa especie '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)];
        $filas[] = (object) ['taxon_id' => $id, 'total' => $i > 20 ? 4 : 1];
        $validos[] = $id;
    }
    $resumen = ResumenEspeciesPublicas::desde($filas, $taxones, $validos);
    $inverso = ResumenEspeciesPublicas::desde(array_reverse($filas), $taxones, $validos);
    expect($resumen)->toBe($inverso)->and($resumen['especies'])->toHaveCount(20)
        ->and($resumen['especies'][0])->toBe(['nombre' => 'Qa especie 21', 'total' => 4])
        ->and($resumen['especies'][19])->toBe(['nombre' => 'Qa especie 15', 'total' => 1])
        ->and($resumen['raras'])->toHaveCount(12)
        ->and($resumen['raras'][11])->toBe(['nombre' => 'Qa especie 12', 'total' => 1]);
    expect(ResumenEspeciesPublicas::desde([], $taxones, $validos))->toBe(['especies' => [], 'raras' => []]);
});
