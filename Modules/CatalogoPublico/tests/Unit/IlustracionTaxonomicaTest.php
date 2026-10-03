<?php

use Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica;

it('la fotografía coincide con la especie seleccionada y conserva su identificación y procedencia', function () {
    $imagen = IlustracionTaxonomica::paraTaxon([
        'species' => 'Atta cephalotes', 'genus' => 'Atta', 'family' => 'Formicidae', 'order' => 'Hymenoptera', 'phylum' => 'Arthropoda',
    ]);
    expect(parse_url($imagen['url'], PHP_URL_PATH))->toBe('/images/taxonomia/fotografias/atta-cephalotes.webp')
        ->and(parse_url($imagen['url'], PHP_URL_QUERY))->toBe('v=20261002-fuentes1')
        ->and($imagen['grupo'])->toBe('Formicidae')
        ->and($imagen['representativa'])->toBeTrue()
        ->and($imagen['foto_real'])->toBeTrue()->and($imagen['morfologia'])->toBeTrue()
        ->and($imagen['family'])->toBe('Formicidae')->and($imagen['genus'])->toBe('Atta')
        ->and($imagen['species'])->toBe('Atta cephalotes')
        ->and($imagen['alt'])->toContain('Fotografía de Atta cephalotes')
        ->and($imagen['credito_ecuador'])->toBeTrue()->and($imagen['pais_fotografia'])->toBe('Ecuador')
        ->and($imagen['fuente'])->not->toBeEmpty()->and($imagen['autor'])->not->toBeEmpty()
        ->and($imagen['licencia'])->not->toBeEmpty()->and($imagen['licencia_url'])->toStartWith('https://');
});

it('una especie sin fotografía propia no recibe fotos de su género ni de su familia', function () {
    foreach (['Especie no identificada', 'Camponotus femoratus', 'Camponotus sericeiventris'] as $especie) {
        $imagen = IlustracionTaxonomica::paraTaxon(['species' => $especie, 'genus' => 'Camponotus', 'family' => 'Formicidae']);
        expect($imagen['morfologia'])->toBeFalse()->and($imagen['foto_real'])->toBeFalse()
            ->and($imagen['url'])->toBeNull();
    }
    $generoDesconocido = IlustracionTaxonomica::paraTaxon(['genus' => 'GeneroSinFoto', 'family' => 'Formicidae']);
    expect($generoDesconocido['url'])->toBeNull()->and($generoDesconocido['foto_real'])->toBeFalse();
});

it('los recursos compartidos están disponibles y son ligeros', function () {
    foreach (IlustracionTaxonomica::catalogo() as $grupo) {
        $ruta = dirname(__DIR__, 4).'/public/images/taxonomia/fotografias/'.$grupo.'.webp';
        expect(is_file($ruta))->toBeTrue()->and(filesize($ruta))->toBeLessThan(20000);
    }
});

it('el mosaico de Formicidae ofrece cuatro fotografías identificadas distintas de la misma familia', function () {
    $imagenes = IlustracionTaxonomica::mosaicoParaTaxon(['family' => 'Formicidae']);
    expect($imagenes)->toHaveCount(4)
        ->and(array_unique(array_column($imagenes, 'url')))->toHaveCount(4)
        ->and(array_column($imagenes, 'species'))->toEqualCanonicalizing(['Linepithema humile', 'Dolichoderus bispinosus', 'Atta cephalotes', 'Paratrechina longicornis']);
    foreach ($imagenes as $imagen) {
        $ruta = dirname(__DIR__, 4).'/public'.parse_url($imagen['url'], PHP_URL_PATH);
        expect($imagen['representativa'])->toBeTrue()
            ->and($imagen['foto_real'])->toBeTrue()->and($imagen['family'])->toBe('Formicidae')
            ->and($imagen['credito_ecuador'])->toBeTrue()->and($imagen['pais_fotografia'])->toBe('Ecuador')
            ->and($imagen['alt'])->toContain($imagen['species'])->and($imagen['genus'])->not->toBeEmpty()
            ->and($imagen['fuente'])->not->toBeEmpty()->and($imagen['licencia'])->not->toBeEmpty()
            ->and(is_file($ruta))->toBeTrue()
            ->and(filesize($ruta))->toBeLessThan(20000);
    }
});
