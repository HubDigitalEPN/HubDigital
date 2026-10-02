<?php

use Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica;

it('elige el ancestro morfológico más específico sin atribuir un dibujo a un ejemplar real', function () {
    $imagen = IlustracionTaxonomica::paraTaxon([
        'species' => 'Camponotus femoratus', 'family' => 'Formicidae', 'order' => 'Hymenoptera', 'phylum' => 'Arthropoda',
    ]);
    expect($imagen['url'])->toBe('/images/taxonomia/formicidae.webp')
        ->and($imagen['grupo'])->toBe('Formicidae')
        ->and($imagen['representativa'])->toBeTrue()
        ->and($imagen['alt'])->toContain('No es una fotografía');
});

it('no inventa la morfología de una especie sin linaje conocido', function () {
    $imagen = IlustracionTaxonomica::paraTaxon(['species' => 'Especie no identificada']);
    expect($imagen['morfologia'])->toBeFalse()
        ->and($imagen['url'])->toBe('/images/taxonomia/invertebrados.svg');
});

it('los recursos compartidos están disponibles y son ligeros', function () {
    foreach (IlustracionTaxonomica::catalogo() as $grupo) {
        $ruta = dirname(__DIR__, 4).'/public/images/taxonomia/'.$grupo.'.webp';
        expect(is_file($ruta))->toBeTrue()->and(filesize($ruta))->toBeLessThan(20000);
    }
});

it('el mosaico de hormigas ofrece cuatro ilustraciones explícitas y distintas', function () {
    $imagenes = IlustracionTaxonomica::mosaicoParaTaxon(['family' => 'Formicidae']);
    expect($imagenes)->toHaveCount(4)
        ->and(array_unique(array_column($imagenes, 'url')))->toHaveCount(4);
    foreach ($imagenes as $imagen) {
        expect($imagen['representativa'])->toBeTrue()
            ->and(is_file(dirname(__DIR__, 4).'/public'.$imagen['url']))->toBeTrue();
    }
});
