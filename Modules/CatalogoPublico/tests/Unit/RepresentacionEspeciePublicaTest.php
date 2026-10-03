<?php

uses(Tests\InfrastructureTestCase::class);

function representacionEspeciePublicaDom(string $nombre, array $jerarquia): DOMXPath
{
    return fotografiaPublicaDom(view('catalogopublico::components.representacion-especie', [
        'nombre' => $nombre, 'jerarquia' => $jerarquia,
    ])->render());
}

function fotografiaTaxonomicaPublicaDom(array $imagen): DOMXPath
{
    return fotografiaPublicaDom(view('catalogopublico::components.fotografia-taxonomica', ['imagen' => $imagen])->render());
}

function fotografiaPublicaDom(string $html): DOMXPath
{
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

it('la ficha conserva su nombre y clasificación y solo muestra una fotografía de esa misma especie', function () {
    $linaje = ['phylum' => 'Arthropoda', 'class' => 'Insecta', 'order' => 'Hymenoptera', 'family' => 'Formicidae', 'genus' => 'Camponotus'];
    $primera = representacionEspeciePublicaDom('Camponotus femoratus', $linaje);
    $segunda = representacionEspeciePublicaDom('Atta cephalotes', array_replace($linaje, ['genus' => 'Atta']));

    expect($primera->query('//figure')->item(0)->getAttribute('data-representacion-taxon'))->toBe('Camponotus femoratus')
        ->and($segunda->query('//figure')->item(0)->getAttribute('data-representacion-taxon'))->toBe('Atta cephalotes')
        ->and($primera->query('//figure')->item(0)->getAttribute('aria-label'))->toContain('Camponotus femoratus')
        ->and($segunda->query('//figure')->item(0)->getAttribute('aria-label'))->toContain('Atta cephalotes')
        ->and($primera->query('//dl/div[dt="Especie"]/dd')->item(0)->textContent)->toBe('Camponotus femoratus')
        ->and($segunda->query('//dl/div[dt="Especie"]/dd')->item(0)->textContent)->toBe('Atta cephalotes')
        ->and($primera->query('//img[@src]')->length)->toBe(0)->and($primera->query('//svg')->length)->toBe(0)
        ->and(parse_url($segunda->query('//img')->item(0)->getAttribute('src'), PHP_URL_PATH))->toBe('/images/taxonomia/fotografias/atta-cephalotes.webp')
        ->and(parse_url($segunda->query('//img')->item(0)->getAttribute('src'), PHP_URL_QUERY))->toBe('v=20261002-fuentes1')
        ->and($segunda->query('//img')->item(0)->getAttribute('alt'))->toContain('Fotografía de Atta cephalotes')
        ->and($primera->query('//figure[@data-representacion-taxon]/details/summary')->item(0)->textContent)->toContain('Clasificación pública')
        ->and($primera->query('//figure[@data-representacion-taxon]/details')->item(0)->hasAttribute('open'))->toBeTrue()
        ->and($primera->query('//p[contains(text(),"No hay una fotografía identificada disponible") ]')->length)->toBe(1)
        ->and($segunda->query('//details/summary[contains(text(),"Fuente y licencia")]')->length)->toBe(1)
        ->and($segunda->query('//figure[@data-fotografia-taxonomica="Atta cephalotes"]//figcaption')->item(0)->textContent)->toContain('Formicidae', 'Atta', 'Atta cephalotes', 'fuente externa')
        ->and($segunda->query('//figure[@data-fotografia-taxonomica]//a[@target="_blank"]')->length)->toBe(2);
    foreach ($linaje as $ancestro) {
        expect($primera->query('//dl//dd[text()="'.$ancestro.'"]')->length)->toBe(1);
    }
});

it('cambiar la identidad o el contexto sustituye el estado cliente de fotografías y conserva estable la misma selección', function () {
    $taxon = ['family' => 'Formicidae', 'species' => 'Camponotus femoratus'];
    $render = static fn (array $seleccion, string $contexto): DOMXPath => fotografiaPublicaDom(
        view('catalogopublico::components.fotografia-mosaico', ['taxon' => $seleccion, 'fotos' => [], 'contexto' => $contexto])->render()
    );
    $primero = $render($taxon, 'ficha-mapa')->query('//*[@*[name()="wire:key"]]')->item(0);
    $repetido = $render($taxon, 'ficha-mapa')->query('//*[@*[name()="wire:key"]]')->item(0);
    $otraEspecie = $render(array_replace($taxon, ['species' => 'Camponotus sericeiventris']), 'ficha-mapa')->query('//*[@*[name()="wire:key"]]')->item(0);
    $ayuda = $render($taxon, 'ayuda-taxon')->query('//*[@*[name()="wire:key"]]')->item(0);
    expect($primero->getAttribute('wire:key'))->not->toBeEmpty()
        ->and($repetido->getAttribute('wire:key'))->toBe($primero->getAttribute('wire:key'))
        ->and($otraEspecie->getAttribute('wire:key'))->not->toBe($primero->getAttribute('wire:key'))
        ->and($ayuda->getAttribute('wire:key'))->not->toBe($primero->getAttribute('wire:key'))
        ->and($primero->getAttribute('x-data'))->toContain('Camponotus femoratus')->not->toContain('Camponotus sericeiventris')
        ->and($otraEspecie->getAttribute('x-data'))->toContain('Camponotus sericeiventris')->not->toContain('Camponotus femoratus');
});

it('sin morfología conocida conserva el linaje recibido y no reconstruye rangos ausentes', function () {
    $dom = representacionEspeciePublicaDom('Taxon alpha', ['kingdom' => 'Animalia', 'phylum' => 'Taxonphylum']);

    expect($dom->query('//img[@src]')->length)->toBe(0)
        ->and($dom->query('//dl//dd[text()="Animalia"]')->length)->toBe(1)
        ->and($dom->query('//dl//dd[text()="Taxonphylum"]')->length)->toBe(1)
        ->and($dom->query('//dl//dd[text()="Taxon alpha"]')->length)->toBe(1)
        ->and($dom->query('//dl//dt[text()="Familia" or text()="Género"]')->length)->toBe(0)
        ->and($dom->query('//figure[@data-representacion-taxon]/details')->item(0)->hasAttribute('open'))->toBeTrue()
        ->and($dom->query('//p[contains(text(),"No hay una fotografía identificada disponible")]')->length)->toBe(1)
        ->and($dom->query('//svg')->length)->toBe(0);
});

it('la fotografía identificada no reconstruye ancestros retirados del linaje público', function () {
    $dom = representacionEspeciePublicaDom('Atta cephalotes', [
        'family' => 'Familia reservada', 'genus' => 'Género reservado',
        'ancestros' => [['rango' => 'reino', 'nombre' => 'Animalia']],
    ]);
    expect($dom->query('//img')->length)->toBe(1)
        ->and($dom->query('//img')->item(0)->getAttribute('alt'))->not->toContain('Formicidae')
        ->and($dom->query('//figure[@data-fotografia-taxonomica]//figcaption')->item(0)->textContent)->not->toContain('Formicidae', 'Familia reservada', 'Género reservado')
        ->and($dom->query('//figure[@data-fotografia-taxonomica]//em')->length)->toBe(1)
        ->and($dom->query('//dl//dt[text()="Familia" or text()="Género"]')->length)->toBe(0)
        ->and($dom->query('//dl/div[dt="Especie"]/dd')->item(0)->textContent)->toBe('Atta cephalotes');

    $generoOmitido = representacionEspeciePublicaDom('Atta cephalotes', [
        'ancestros' => [
            ['rango' => 'reino', 'nombre' => 'Animalia'],
            ['rango' => 'familia', 'nombre' => 'Formicidae'],
        ],
    ]);
    expect($generoOmitido->query('//img')->length)->toBe(1)
        ->and(parse_url($generoOmitido->query('//img')->item(0)->getAttribute('src'), PHP_URL_PATH))
        ->toBe('/images/taxonomia/fotografias/atta-cephalotes.webp')
        ->and($generoOmitido->query('//figure[@data-fotografia-taxonomica="Atta cephalotes"]/figcaption')->item(0)->textContent)
        ->toContain('Formicidae', 'Atta cephalotes')->not->toContain('género Atta')
        ->and($generoOmitido->query('//figure[@data-fotografia-taxonomica="Atta cephalotes"]/figcaption/p[2]')->item(0)->textContent)
        ->toBe('Fotografía identificada de Atta cephalotes.')
        ->and($generoOmitido->query('//dl/div[dt="Familia"]/dd')->item(0)->textContent)->toBe('Formicidae')
        ->and($generoOmitido->query('//dl//dt[text()="Género"]')->length)->toBe(0)
        ->and($generoOmitido->query('//dl/div[dt="Especie"]/dd')->item(0)->textContent)->toBe('Atta cephalotes');
});

it('la autoría visible se reserva a referencias verificadas en Ecuador y siempre conserva fuente y licencia', function () {
    $ecuador = \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon([
        'species' => 'Atta cephalotes', 'genus' => 'Atta', 'family' => 'Formicidae',
    ]);
    $internacional = \Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica::paraTaxon([
        'species' => 'Cornu aspersum', 'genus' => 'Cornu', 'family' => 'Helicidae',
    ]);
    // Conservar la procedencia no obliga a imprimir un crédito internacional en esta interfaz.
    $internacional['autor'] = 'Autor de la fuente internacional';
    $internacional['descripcion'] = 'Fotografía identificada como Cornu aspersum.';
    $domEcuador = fotografiaTaxonomicaPublicaDom($ecuador);
    $domInternacional = fotografiaTaxonomicaPublicaDom($internacional);
    expect($ecuador['credito_ecuador'])->toBeTrue()->and($internacional['credito_ecuador'])->toBeFalse()
        ->and($domEcuador->query('//p[contains(text(),"Autoría:")]')->length)->toBe(1)
        ->and($domEcuador->query('//figcaption')->item(0)->textContent)->toContain($ecuador['autor'], 'Ecuador')
        ->and($domInternacional->query('//p[contains(text(),"Autoría:")]')->length)->toBe(0)
        ->and($domInternacional->query('//figcaption')->item(0)->textContent)->not->toContain('Autor de la fuente internacional');
    foreach ([[$domEcuador, $ecuador], [$domInternacional, $internacional]] as [$dom, $imagen]) {
        expect($dom->query('//img')->length)->toBe(1)
            ->and($dom->query('//a[@href="'.$imagen['fuente'].'"]')->length)->toBe(1)
            ->and($dom->query('//a[@href="'.$imagen['licencia_url'].'"]')->length)->toBe(1)
            ->and($dom->query('//a[@href="'.$imagen['licencia_url'].'"]')->item(0)->textContent)->toBe($imagen['licencia'])
            ->and($dom->query('//details')->item(0)->textContent)->toContain($imagen['cambios']);
    }
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
        ->and($dom->query('//img[@src]')->length)->toBe(0)
        ->and($dom->query('//dl/div[dt="Género"]/dd')->item(0)->textContent)->toBe('Neoponera');
});

it('la ayuda de especie excluye metadatos y conserva los seis rangos públicos una sola vez', function (string $nombre, array $linaje, bool $conFoto) {
    $uuid = 'e8975101-989e-4148-848a-c25a8be34049';
    $entrada = $linaje + ['id' => $uuid, 'padre' => 'Metadato padre', 'especie' => $nombre,
        'species' => $nombre, 'nota' => 'Metadato de colecta', 'total' => 8];
    $dom = representacionEspeciePublicaDom($nombre, $entrada);

    expect($dom->query('//figure[@data-representacion-taxon]/details/summary')->item(0)->textContent)->toContain('6 rangos')
        ->and($dom->query('//figure[@data-representacion-taxon]/details/dl/div')->length)->toBe(6)
        ->and($dom->query('//dl/div[dt="Especie"]')->length)->toBe(1)
        ->and($dom->query('//dl/div[dt="Especie"]/dd')->item(0)->textContent)->toBe($nombre)
        ->and($dom->query('//dl//dt[text()="Id" or text()="Padre" or text()="Nota" or text()="Total"]')->length)->toBe(0)
        ->and($dom->query('//dl')->item(0)->textContent)->not->toContain($uuid, 'Metadato')
        ->and($dom->query('//img[@src]')->length)->toBe($conFoto ? 1 : 0);
    foreach ($linaje as $ancestro) expect($dom->query('//dl//dd[text()="'.$ancestro.'"]')->length)->toBe(1);
})->with([
    'Aulacomya sin fotografía' => ['Aulacomya atra', ['phylum' => 'Mollusca', 'class' => 'Bivalvia', 'order' => 'Mytiloida', 'family' => 'Mytilidae', 'genus' => 'Aulacomya'], false],
    'Atta con fotografía identificada' => ['Atta cephalotes', ['phylum' => 'Arthropoda', 'class' => 'Insecta', 'order' => 'Hymenoptera', 'family' => 'Formicidae', 'genus' => 'Atta'], true],
]);

it('el linaje explícito descarta pseudorrangos y deduplica aliases sin reconstruir datos reservados', function () {
    $dom = representacionEspeciePublicaDom('Taxon alpha', ['family' => 'Familia reservada', 'ancestros' => [
        ['rango' => 'reino', 'nombre' => 'Animalia'], ['rango' => 'kingdom', 'nombre' => 'Animalia'],
        ['rango' => 'id', 'nombre' => 'UUID técnico'], ['rango' => 'padre', 'nombre' => 'Padre técnico'],
        ['rango' => 'especie', 'nombre' => 'Taxon alpha'], ['rango' => 'species', 'nombre' => 'Taxon alpha'],
        ['rango' => 'species', 'nombre' => 'Otra especie'],
    ]]);

    expect($dom->query('//dl/div')->length)->toBe(2)
        ->and($dom->query('//dl/div[dt="Reino"]')->length)->toBe(1)
        ->and($dom->query('//dl/div[dt="Especie"]')->length)->toBe(1)
        ->and($dom->query('//dl')->item(0)->textContent)->not->toContain('técnico', 'reservada', 'Otra especie');
});
