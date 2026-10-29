<?php

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

uses(\Tests\DatabaseFeatureTestCase::class);

test('tarjetas y navegación superior conservan la selección izquierda por UUID y geografía', function () {
    $f = qa7ColeccionFixture();
    Livewire::withQueryParams(['fti' => $f['taxon']])->test(PortalCatalogo::class)
        ->set('filtroProvincia', 'Napo')->call('cambiarVista', 'tarjetas')
        ->assertViewHas('hijos', fn ($hijos) => count($hijos) === 1 && $hijos[0]['total'] === 1)
        ->call('explorarNivel', 'species')->assertSet('filtroTaxonId', $f['taxon'])->assertSet('filtroProvincia', 'Napo')
        ->assertViewHas('taxonesExplorados', fn ($taxones) => count($taxones) === 1 && $taxones[0]['taxon'] === 'Qaheptus alpha')
        ->call('navegar', 'species', 'Qaheptus alpha')->assertSet('filtroTaxonId', $f['taxon'])
        ->assertViewHas('especimenes', fn ($registros) => count($registros) === 1 && $registros[0]->especimen_id === $f['ids'][2]);
});

test('el scroll acumula sin duplicar y rechaza peticiones de una selección anterior', function () {
    $f = qa7ColeccionFixture(cantidad: 15);
    $c = Livewire::withQueryParams(['fti' => $f['taxon']])->test(PortalCatalogo::class)->call('navegar', 'species', 'Qaheptus alpha')
        ->assertViewHas('especimenes', fn ($filas) => count($filas) === 6);
    $clave = $c->get('claveTarjetasCargadas');
    $c->call('cargarMasTarjetas', $clave, 1)->assertViewHas('especimenes', fn ($filas) => count($filas) === 12 && count(array_unique(array_column($filas, 'especimen_id'))) === 12)
        ->call('cargarMasTarjetas', $clave, 1)->assertSet('paginasTarjetasCargadas', 2)
        ->call('cargarMasTarjetas', $clave, 2)->assertViewHas('especimenes', fn ($filas) => count($filas) === 15)
        ->set('filtroProvincia', 'Napo')->assertSet('paginasTarjetasCargadas', 1)
        ->call('cargarMasTarjetas', $clave, 3)->assertSet('paginasTarjetasCargadas', 1)
        ->assertViewHas('especimenes', fn ($filas) => count($filas) === 1);
});

test('la fotografía de tarjeta guarda sus créditos en el visor y la ficha usa etiquetas legibles', function () {
    $html = view('catalogopublico::components.fotografia-tarjeta', ['nombre' => 'Qaheptus alpha', 'nivel' => 'species', 'url' => '/portal/imagenes/foto-qa',
        'compacto' => true, 'datos' => ['atribuciones' => ['photographer' => 'Autora QA']]])->render();
    expect($html)->toContain('Ejemplar de la colección', 'Autora QA')->not->toContain('class="photo-attributions"');
    $f = qa7ColeccionFixture();
    Livewire::withQueryParams(['fti' => $f['taxon']])->test(PortalCatalogo::class)
        ->call('navegar', 'species', 'Qaheptus alpha')->assertSee('collection-species-summary', false)
        ->call('abrirFichaRegistro', $f['ids'][0])->assertHasNoErrors()
        ->assertSee('Ficha del ejemplar')->assertSee('Colector')->assertSee('País')->assertSee('Cerrar ficha del registro');
});
