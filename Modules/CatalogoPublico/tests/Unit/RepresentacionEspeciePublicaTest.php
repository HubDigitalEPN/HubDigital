<?php

uses(Tests\InfrastructureTestCase::class);

function representacionEspeciePublicaDom(string $nombre, array $jerarquia): DOMXPath
{
    $html = view('catalogopublico::components.representacion-especie', [
        'nombre' => $nombre, 'jerarquia' => $jerarquia,
    ])->render();
    $erroresAnteriores = libxml_use_internal_errors(true);
    try {
        $documento = new DOMDocument;
        $documento->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($documento);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($erroresAnteriores);
    }
}

it('cada especie tiene ficha visual propia y comparte solo el retrato generado de su grupo', function () {
    $linaje = ['phylum' => 'Arthropoda', 'class' => 'Insecta', 'order' => 'Hymenoptera', 'family' => 'Formicidae', 'genus' => 'Camponotus'];
    $primera = representacionEspeciePublicaDom('Camponotus femoratus', $linaje);
    $segunda = representacionEspeciePublicaDom('Camponotus sericeiventris', $linaje);

    expect($primera->query('//figure')->item(0)->getAttribute('data-representacion-taxon'))->toBe('Camponotus femoratus')
        ->and($segunda->query('//figure')->item(0)->getAttribute('data-representacion-taxon'))->toBe('Camponotus sericeiventris')
        ->and($primera->query('//figure')->item(0)->getAttribute('aria-label'))->toContain('Camponotus femoratus')
        ->and($segunda->query('//figure')->item(0)->getAttribute('aria-label'))->toContain('Camponotus sericeiventris')
        ->and($primera->query('//dl/div[dt="Especie"]/dd')->item(0)->textContent)->toBe('Camponotus femoratus')
        ->and($segunda->query('//dl/div[dt="Especie"]/dd')->item(0)->textContent)->toBe('Camponotus sericeiventris')
        ->and(parse_url($primera->query('//img')->item(0)->getAttribute('src'), PHP_URL_PATH))->toBe('/images/taxonomia/formicidae.webp')
        ->and(parse_url($segunda->query('//img')->item(0)->getAttribute('src'), PHP_URL_PATH))->toBe('/images/taxonomia/formicidae.webp')
        ->and(parse_url($primera->query('//img')->item(0)->getAttribute('src'), PHP_URL_QUERY))->toBe('v=20261002-foto1')
        ->and($primera->query('//img')->item(0)->getAttribute('alt'))->toContain('Representación fotorrealista generada')
        ->and($primera->query('//details/summary')->item(0)->textContent)->toContain('Clasificación pública')
        ->and($primera->query('//details')->item(0)->hasAttribute('open'))->toBeFalse()
        ->and($primera->query('//figcaption')->item(0)->textContent)->toContain('no identifica la especie');
    foreach ($linaje as $ancestro) {
        expect($primera->query('//dl//dd[text()="'.$ancestro.'"]')->length)->toBe(1);
    }
});

it('sin morfología conocida conserva el linaje recibido y no reconstruye rangos ausentes', function () {
    $dom = representacionEspeciePublicaDom('Taxon alpha', ['kingdom' => 'Animalia', 'phylum' => 'Taxonphylum']);

    expect($dom->query('//img')->length)->toBe(0)
        ->and($dom->query('//dl//dd[text()="Animalia"]')->length)->toBe(1)
        ->and($dom->query('//dl//dd[text()="Taxonphylum"]')->length)->toBe(1)
        ->and($dom->query('//dl//dd[text()="Taxon alpha"]')->length)->toBe(1)
        ->and($dom->query('//dl//dt[text()="Familia" or text()="Género"]')->length)->toBe(0)
        ->and($dom->query('//details')->item(0)->hasAttribute('open'))->toBeTrue()
        ->and($dom->query('//figcaption')->item(0)->textContent)->toContain('No hay una ilustración morfológica disponible');
});

it('los nombres científicos se mantienen como texto y nunca se convierten en marcado activo', function () {
    $nombre = 'Taxon <script>alert(1)</script> & alpha';
    $dom = representacionEspeciePublicaDom($nombre, ['phylum' => 'Taxon<phylum>']);

    expect($dom->query('//script')->length)->toBe(0)
        ->and($dom->query('//figure')->item(0)->getAttribute('data-representacion-taxon'))->toBe($nombre)
        ->and($dom->query('//dl/div[dt="Especie"]/dd')->item(0)->textContent)->toBe($nombre)
        ->and($dom->query('//dl/div[dt="Filo"]/dd')->item(0)->textContent)->toBe('Taxon<phylum>');
});

it('la clasificación conserva rangos intermedios repetidos y desconocidos en el orden público', function () {
    $ancestros = [
        ['rango' => 'reino', 'nombre' => 'Animalia'], ['rango' => 'phylum', 'nombre' => 'Arthropoda'],
        ['rango' => 'clase', 'nombre' => 'Insecta'], ['rango' => 'orden', 'nombre' => 'Hymenoptera'],
        ['rango' => 'suborden', 'nombre' => 'Apocrita'], ['rango' => 'familia', 'nombre' => 'Formicidae'],
        ['rango' => 'subfamilia', 'nombre' => 'Ponerinae'], ['rango' => 'tribu', 'nombre' => 'Ponerini'],
        ['rango' => 'grupo', 'nombre' => 'Grupo alpha'], ['rango' => 'grupo', 'nombre' => 'Grupo beta'],
        ['rango' => 'genero', 'nombre' => 'Neoponera'], ['rango' => 'especie', 'nombre' => 'Neoponera carinulata'],
    ];
    $dom = representacionEspeciePublicaDom('Neoponera carinulata', ['ancestros' => $ancestros]);
    $nombres = array_map(static fn (DOMNode $nodo): string => $nodo->textContent, iterator_to_array($dom->query('//dl//dd')));
    expect($nombres)->toBe(array_column($ancestros, 'nombre'))
        ->and($dom->query('//dl/div[dt="Suborden"]/dd')->item(0)->textContent)->toBe('Apocrita')
        ->and($dom->query('//dl/div[dt="Subfamilia"]/dd')->item(0)->textContent)->toBe('Ponerinae')
        ->and($dom->query('//dl/div[dt="Tribu"]/dd')->item(0)->textContent)->toBe('Ponerini')
        ->and($dom->query('//dl/div[dt="Grupo"]')->length)->toBe(2)
        ->and($dom->query('//dl/div[dt="Especie"]')->length)->toBe(1);
});

it('la fuente pública de ancestros prevalece y no reconstruye una familia ausente', function () {
    $dom = representacionEspeciePublicaDom('Neoponera carinulata', [
        'family' => 'Familia reservada',
        'ancestros' => [['rango' => 'reino', 'nombre' => 'Animalia'], ['rango' => 'genero', 'nombre' => 'Neoponera']],
    ]);
    expect($dom->query('//dl//dt[text()="Familia"]')->length)->toBe(0)
        ->and($dom->query('//dl//dd[text()="Familia reservada"]')->length)->toBe(0)
        ->and($dom->query('//img')->length)->toBe(0)
        ->and($dom->query('//dl/div[dt="Género"]/dd')->item(0)->textContent)->toBe('Neoponera');
});
