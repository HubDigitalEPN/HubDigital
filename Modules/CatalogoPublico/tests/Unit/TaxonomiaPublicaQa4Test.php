<?php

use Modules\CatalogoPublico\Application\Services\IlustracionTaxonomica;
use Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico;

it('QA4 no rellena mosaicos vacíos ni Mollusca con grupos ajenos a sus linajes', function (): void {
    expect(IlustracionTaxonomica::mosaicoParaTaxon([])[0]['morfologia'])->toBeFalse()
        ->and(IlustracionTaxonomica::mosaicoParaSeleccion([['phylum' => 'Mollusca', 'total' => 8]])[0]['morfologia'])->toBeFalse()
        ->and(array_column(IlustracionTaxonomica::mosaicoParaSeleccion([['phylum' => 'Mollusca', 'class' => 'Gastropoda']]), 'grupo'))->toBe(['Gastropoda']);
});

it('QA4 limita el mosaico a cuatro grupos realmente presentes y conserva cuatro hormigas', function (): void {
    $seleccion = [['order' => 'Coleoptera'], ['class' => 'Gastropoda'], ['order' => 'Diptera'], ['order' => 'Hemiptera'], ['order' => 'Isopoda']];
    $imagenes = IlustracionTaxonomica::mosaicoParaSeleccion($seleccion);
    expect($imagenes)->toHaveCount(4)->and(array_column($imagenes, 'grupo'))->not->toContain('Formicidae', 'Araneae', 'Lepidoptera')
        ->and(IlustracionTaxonomica::mosaicoParaSeleccion([['family' => 'Formicidae']]))->toHaveCount(4);
});

it('QA4 conserva el prefijo real y excluye marcadores, sus hijos y ciclos con límite de treinta nodos', function (): void {
    $ruta = [['nombre' => 'Arthropoda'], ['nombre' => 'muestra reubicada dentro de 1269'], ['nombre' => 'Nombre inferior']];
    expect(CalidadDatoPublico::rutaConfirmada($ruta))->toBe([['nombre' => 'Arthropoda']]);
    $taxones = [
        (object) ['id' => 'raiz', 'padre_id' => null, 'nombre_cientifico' => 'Arthropoda'],
        (object) ['id' => 'nota', 'padre_id' => 'raiz', 'nombre_cientifico' => 'dañada'],
        (object) ['id' => 'hijo', 'padre_id' => 'nota', 'nombre_cientifico' => 'Nombre conocido'],
        (object) ['id' => 'ciclo', 'padre_id' => 'ciclo', 'nombre_cientifico' => 'Nombre circular'],
        (object) ['id' => 'parcial', 'padre_id' => 'ausente', 'nombre_cientifico' => 'Nombre parcial'],
    ];
    foreach (range(0, 30) as $i) $taxones[] = (object) ['id' => 'profundo'.$i, 'padre_id' => $i ? 'profundo'.($i - 1) : null, 'nombre_cientifico' => 'Nombre '.$i];
    $validos = CalidadDatoPublico::taxonesConLinajeValido($taxones);
    expect($validos)->toContain('raiz', 'parcial', 'profundo29')->not->toContain('nota', 'hijo', 'ciclo', 'profundo30');
});
