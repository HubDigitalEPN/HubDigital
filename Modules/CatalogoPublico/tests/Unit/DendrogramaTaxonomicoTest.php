<?php

use Modules\CatalogoPublico\Application\Services\DendrogramaTaxonomico;

uses(Tests\InfrastructureTestCase::class);

function nodosDendrogramaPublico(): array
{
    $cadena = [
        ['Animalia', 'reino'], ['Arthropoda', 'phylum'], ['Insecta', 'clase'],
        ['Hymenoptera', 'orden'], ['Apocrita', 'suborden'], ['Formicidae', 'familia'],
        ['Ponerinae', 'subfamilia'], ['Ponerini', 'tribu'], ['Neoponera', 'genero'],
    ];
    $nodos = [];
    foreach ($cadena as $indice => [$nombre, $rango]) {
        $nodos[] = ['id' => 'rango-'.$indice, 'padre_id' => $indice > 0 ? 'rango-'.($indice - 1) : null, 'nombre' => $nombre, 'rango' => $rango, 'total' => 10];
    }
    $nodos[] = ['id' => 'especie-1', 'padre_id' => 'rango-8', 'nombre' => 'Neoponera carinulata', 'rango' => 'especie', 'total' => 4];
    $nodos[] = ['id' => 'especie-2', 'padre_id' => 'rango-8', 'nombre' => 'Neoponera apicalis', 'rango' => 'especie', 'total' => 6];

    return $nodos;
}

it('representa diez rangos y bifurcaciones reales con geometría compacta y selección coherente', function () {
    $fuente = nodosDendrogramaPublico();
    $grafico = DendrogramaTaxonomico::calcular(array_reverse($fuente), 'especie-2');
    $nodos = array_column($grafico['nodos'], null, 'id');
    $ramas = array_column($grafico['ramas'], null, 'hijo_id');

    expect($grafico['nodos'])->toHaveCount(11)
        ->and($grafico['ramas'])->toHaveCount(10)
        ->and($grafico['ancho'])->toBe(640)
        ->and($grafico['alto'])->toBeLessThan(600)
        ->and($nodos['rango-4']['etiqueta'])->toBe('Suborden')
        ->and($nodos['rango-6']['etiqueta'])->toBe('Subfamilia')
        ->and($nodos['rango-7']['etiqueta'])->toBe('Tribu')
        ->and($nodos['rango-8']['etiqueta'])->toBe('Género')
        ->and($nodos['especie-2']['seleccionado'])->toBeTrue()
        ->and($nodos['especie-1']['seleccionado'])->toBeFalse()
        ->and($ramas['especie-1']['padre_id'])->toBe('rango-8')
        ->and($ramas['especie-2']['padre_id'])->toBe('rango-8')
        ->and($ramas['especie-2']['activa'])->toBeTrue()
        ->and($ramas['especie-1']['activa'])->toBeFalse()
        ->and($nodos['especie-1']['miniatura']['grupo'])->toBe('Formicidae');
    foreach ($fuente as $nodo) {
        $dibujado = $nodos[$nodo['id']];
        expect($dibujado['nombre'])->toBe($nodo['nombre'])
            ->and($dibujado['rango'])->toBe($nodo['rango'])
            ->and($dibujado['total'])->toBe($nodo['total'])
            ->and($dibujado['x'])->toBeLessThan(320)
            ->and($dibujado['y'])->toBeGreaterThan(0)
            ->and($dibujado['y'])->toBeLessThan($grafico['alto']);
        if ($nodo['padre_id'] !== null) {
            expect($ramas[$nodo['id']]['padre_id'])->toBe($nodo['padre_id'])
                ->and($nodos[$nodo['padre_id']]['x'])->toBeLessThan($dibujado['x'])
                ->and($nodos[$nodo['padre_id']]['y'])->toBeLessThan($dibujado['y']);
        }
    }
});

it('una sola especie conserva una cadena sin bifurcaciones ni ancestros inferidos', function () {
    $fuente = array_values(array_filter(nodosDendrogramaPublico(), static fn (array $nodo): bool => $nodo['id'] !== 'especie-2'));
    $grafico = DendrogramaTaxonomico::calcular($fuente, 'especie-1');
    $terminales = array_values(array_filter($grafico['nodos'], static fn (array $nodo): bool => $nodo['hoja']));
    $ramasPorPadre = array_count_values(array_column($grafico['ramas'], 'padre_id'));
    expect($terminales)->toHaveCount(1)
        ->and($terminales[0]['id'])->toBe('especie-1')
        ->and(max($ramasPorPadre))->toBe(1);

    $parcial = DendrogramaTaxonomico::calcular([
        ['id' => 'parcial', 'padre_id' => 'familia-no-publicada', 'nombre' => 'Taxon alpha', 'rango' => 'especie', 'total' => 1],
    ]);
    expect($parcial['nodos'])->toHaveCount(1)
        ->and($parcial['ramas'])->toBe([])
        ->and($parcial['nodos'][0]['padre_nombre'])->toBeNull()
        ->and($parcial['nodos'][0]['miniatura'])->toBeNull();
});

it('conserva claves de linaje distintas para el mismo UUID taxonómico y corta ciclos inválidos', function () {
    $grafico = DendrogramaTaxonomico::calcular([
        ['id' => 'animalia', 'padre_id' => null, 'nombre' => 'Animalia', 'rango' => 'reino', 'total' => 3],
        ['id' => 'taxon-uuid:publico', 'taxon_id' => 'taxon-uuid', 'padre_id' => 'animalia', 'nombre' => 'Taxon alpha', 'rango' => 'especie', 'total' => 1],
        ['id' => 'taxon-uuid:parcial', 'taxon_id' => 'taxon-uuid', 'padre_id' => null, 'nombre' => 'Taxon alpha', 'rango' => 'especie', 'total' => 2],
    ], 'taxon-uuid:parcial');
    $nodos = array_column($grafico['nodos'], null, 'id');
    expect($grafico['nodos'])->toHaveCount(3)
        ->and($grafico['ramas'])->toHaveCount(1)
        ->and($nodos['taxon-uuid:publico']['total'])->toBe(1)
        ->and($nodos['taxon-uuid:parcial']['total'])->toBe(2)
        ->and($nodos['taxon-uuid:parcial']['seleccionado'])->toBeTrue();

    $ciclo = DendrogramaTaxonomico::calcular([
        ['id' => 'a', 'padre_id' => 'b', 'nombre' => 'Taxon a', 'rango' => 'genero', 'total' => 1],
        ['id' => 'b', 'padre_id' => 'a', 'nombre' => 'Taxon b', 'rango' => 'especie', 'total' => 1],
    ], 'a');
    expect($ciclo['nodos'])->toHaveCount(2)->and($ciclo['ramas'])->toBe([]);
});

it('el gráfico renderiza controles nativos y relaciones públicas con nombres escapados', function () {
    $nodos = nodosDendrogramaPublico();
    $nodos[10]['nombre'] = 'Taxon <script>alert(1)</script> & alpha';
    $html = view('catalogopublico::components.dendrograma-mapa', ['arbol' => $nodos, 'seleccionado' => 'especie-1'])->render();
    $erroresAnteriores = libxml_use_internal_errors(true);
    try {
        $documento = new DOMDocument;
        $documento->loadHTML('<?xml encoding="UTF-8">'.$html);
        $dom = new DOMXPath($documento);
        expect($dom->query('//button[@type="button"]')->length)->toBe(11)
            ->and($dom->query('//button[@aria-pressed="true" and @data-taxon-id="especie-1"]')->length)->toBe(1)
            ->and($dom->query('//path[@data-padre-id="rango-8" and @data-hijo-id="especie-1"]')->length)->toBe(1)
            ->and($dom->query('//path[@data-padre-id="rango-8" and @data-hijo-id="especie-2"]')->length)->toBe(1)
            ->and($dom->query('//button[@data-taxon-id="especie-1"]')->item(0)->getAttribute('aria-label'))->toContain('Taxón padre: Neoponera')
            ->and($dom->query('//button[@data-taxon-id="especie-2"]//strong')->item(0)->textContent)->toBe($nodos[10]['nombre'])
            ->and($dom->query('//script')->length)->toBe(0)
            ->and($dom->query('//svg[@aria-hidden="true"]')->length)->toBe(1);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($erroresAnteriores);
    }
});
