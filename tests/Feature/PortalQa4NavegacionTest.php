<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

uses(Tests\DatabaseFeatureTestCase::class);

/** Fixture equivalente al recorrido especie→orden, aislado por provincia pública. */
function qa4NavegacionFixture(): array
{
    $sufijo = Str::lower(preg_replace('/[^a-zA-Z]/', '', Str::random(28)));
    $provincia = 'IslasQA'.$sufijo;
    $filo = DB::table('taxonomia.taxones')->where('rango', 'phylum')->where('nombre_cientifico', 'Mollusca')->value('id');
    if ($filo === null) {
        $filo = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $filo, 'nombre_cientifico' => 'Mollusca', 'rango' => 'phylum', 'autor' => 'QA', 'anio_descripcion' => 2026]);
    }
    $nombres = ['clase' => 'Bivalviaqa'.$sufijo, 'orden' => 'Mytiloidaqa'.$sufijo, 'familia' => 'Mytilidaeqa'.$sufijo, 'genero' => 'Aulacomyaqa'.$sufijo];
    $idsTaxones = [];
    $padre = $filo;
    foreach ($nombres as $rango => $nombre) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $id, 'padre_id' => $padre, 'nombre_cientifico' => $nombre, 'rango' => $rango, 'autor' => 'QA', 'anio_descripcion' => 2026]);
        $idsTaxones[$rango] = $id;
        $padre = $id;
    }
    $especies = [];
    foreach (['atra', 'alfa'] as $epiteto) {
        $nombre = $nombres['genero'].' '.$epiteto;
        $id = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $id, 'padre_id' => $padre, 'nombre_cientifico' => $nombre, 'rango' => 'especie', 'autor' => 'QA', 'anio_descripcion' => 2026]);
        $especies[$epiteto] = ['id' => $id, 'nombre' => $nombre];
    }
    $codigos = [];
    foreach (range(0, 7) as $indice) {
        $id = (string) Str::uuid();
        $codigo = 'QA4-NAV-'.Str::upper(Str::random(18));
        DB::table('taxonomia.especimenes')->insert([
            'id' => $id, 'taxon_id' => $especies[$indice === 0 ? 'atra' : 'alfa']['id'],
            'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo,
            'state_province' => $provincia, 'country' => 'Ecuador', 'localidad' => 'Isla QA', 'locality_name' => 'Isla QA',
            'decimal_latitude' => -0.63194, 'decimal_longitude' => -76.14416,
            'fecha_colecta' => '2000-05-15', 'life_stage' => 'adult',
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true]);
        $codigos[] = $codigo;
    }

    return compact('filo', 'provincia', 'nombres', 'idsTaxones', 'especies', 'codigos');
}

test('rango taxón vista y filtros se restauran juntos entre especie y orden', function (): void {
    $f = qa4NavegacionFixture();
    $estado = ['vista' => 'tarjetas', 'nivel' => 'species', 'taxon' => $f['especies']['atra']['nombre'], 'fph' => $f['filo'], 'fprov' => $f['provincia'], 'fpais' => 'Ecuador', 'ffd' => '2000-05-01', 'ffh' => '2000-05-31'];
    $portal = Livewire::withQueryParams($estado)->test(PortalCatalogo::class)
        ->assertViewHas('totalEspecimenes', 1)->assertSee($f['codigos'][0])->assertDontSee($f['codigos'][1]);
    $portal->call('navegar', 'order', $f['nombres']['orden'])
        ->assertSet('nivel', 'order')->assertSet('taxon', $f['nombres']['orden'])
        ->assertDispatched('catalogo-estado-url', fn ($evento, $parametros): bool => $parametros['estado']['nivel'] === 'order'
            && $parametros['estado']['taxon'] === $f['nombres']['orden'] && $parametros['estado']['fprov'] === $f['provincia'])
        ->assertViewHas('hijos', fn ($nodos): bool => count($nodos) === 1 && $nodos[0]['taxon'] === $f['nombres']['familia']);
    $portal->call('restaurarSeleccionUrl', $estado, 1)
        ->assertSet('nivel', 'species')->assertSet('taxon', $f['especies']['atra']['nombre'])->assertViewHas('totalEspecimenes', 1)
        ->assertSee($f['codigos'][0])->assertDontSee($f['codigos'][1])
        ->assertDispatched('catalogo-estado-url', fn ($evento, $parametros): bool => $parametros['restauracion'] === 1
            && $parametros['estado']['nivel'] === 'species' && $parametros['estado']['taxon'] === $f['especies']['atra']['nombre']
            && $parametros['estado']['ffd'] === '2000-05-01' && $parametros['estado']['ffh'] === '2000-05-31');
    $portal->call('restaurarSeleccionUrl', ['nivel' => 'order', 'taxon' => $f['nombres']['orden']] + $estado, 2)
        ->assertSet('nivel', 'order')->assertSet('taxon', $f['nombres']['orden'])->assertSet('filtroFiloId', $f['filo']);
});

test('el rango incoherente de un enlace copiado se recupera sólo desde taxonomía publicada', function (): void {
    $f = qa4NavegacionFixture();
    Livewire::withQueryParams(['nivel' => 'order', 'taxon' => $f['especies']['atra']['nombre'], 'fprov' => $f['provincia'], 'vista' => 'registros'])
        ->test(PortalCatalogo::class)->assertSet('nivel', 'species')->assertSet('taxon', $f['especies']['atra']['nombre'])
        ->assertSet('filtroProvincia', $f['provincia'])->assertViewHas('totalRegistrosVista', 1)
        ->assertSee('El rango del enlace se ajustó')->assertSee($f['codigos'][0]);
    Livewire::withQueryParams(['nivel' => 'species', 'taxon' => 'Nombre inexistente '.$f['provincia'], 'fprov' => $f['provincia'], 'vista' => 'registros'])
        ->test(PortalCatalogo::class)->assertSet('nivel', '')->assertSet('taxon', '')->assertSet('filtroProvincia', $f['provincia'])
        ->assertViewHas('totalRegistrosVista', 8)->assertSee('no identifica un taxón público inequívoco');
});

test('un ancestro reservado no se recupera ni se expone al validar la navegación', function (): void {
    $f = qa4NavegacionFixture();
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', DB::table('taxonomia.especimenes')->where('state_province', $f['provincia'])->select('id'))
        ->update(['genus_visible' => false]);
    Livewire::withQueryParams(['nivel' => 'genus', 'taxon' => $f['nombres']['genero'], 'fprov' => $f['provincia'], 'vista' => 'registros'])
        ->test(PortalCatalogo::class)->assertSet('nivel', '')->assertSet('taxon', '')->assertViewHas('totalRegistrosVista', 8)
        ->assertDontSee($f['nombres']['genero'].' · Género');
});

test('composición por UUID y por nombre coincide con el desplegable de filo y mantiene ocho registros', function (): void {
    $f = qa4NavegacionFixture();
    $entrada = ['vista' => 'mapa', 'fprov' => $f['provincia']];
    $portal = Livewire::withQueryParams($entrada)->test(PortalCatalogo::class)
        ->assertSet('filtroFiloId', '')->assertViewHas('datosMapa', fn ($datos): bool => (int) $datos['resumen']['registros'] === 8)
        ->call('seleccionarFilo', $f['filo'])->assertSet('filtroFiloId', $f['filo'])
        ->assertSet('borradorFiltros.filtroFiloId', $f['filo'])
        ->assertDispatched('catalogo-estado-url', fn ($evento, $parametros): bool => $parametros['estado']['fph'] === $f['filo'] && $parametros['estado']['fprov'] === $f['provincia'])
        ->assertViewHas('datosMapa', fn ($datos): bool => (int) $datos['resumen']['registros'] === 8 && $datos['filos']['Mollusca'] === 8);
    $portal->call('seleccionarFilo', ' mollusca ')->assertSet('filtroFiloId', '')
        ->call('seleccionarFilo', 'Mollusca')->assertSet('filtroFiloId', $f['filo'])
        ->call('cambiarVista', 'registros')->assertViewHas('totalRegistrosVista', 8);
    Livewire::withQueryParams($entrada)->test(PortalCatalogo::class)
        ->set('borradorFiltros.filtroFiloId', $f['filo'])->call('aplicarBorrador')->assertSet('filtroFiloId', $f['filo'])
        ->assertViewHas('datosMapa', fn ($datos): bool => (int) $datos['resumen']['registros'] === 8)
        ->call('cambiarVista', 'tarjetas')->assertViewHas('conteos', fn ($conteos): bool => $conteos['phylum:Mollusca'] === 8);
});

test('restaurar una selección elimina filtros abandonados y rechaza los tipos inválidos sin perder los demás', function (): void {
    $f = qa4NavegacionFixture();
    Livewire::withQueryParams(['vista' => 'registros', 'fprov' => $f['provincia'], 'fc' => $f['codigos'][0], 'fpais' => 'Ecuador'])->test(PortalCatalogo::class)
        ->assertViewHas('totalRegistrosVista', 1)
        ->call('restaurarSeleccionUrl', ['vista' => 'registros', 'fprov' => $f['provincia'], 'nivel' => ['species'], 'taxon' => ['oculto'], 'pagina' => '-6', 'fg' => ['Isla QA'], 'ffd' => '2000-05-01', 'ffh' => '2000-05-31'], 3)
        ->assertSet('nivel', '')->assertSet('taxon', '')->assertSet('pagina', 1)
        ->assertSet('filtroCatalogo', '')->assertSet('filtroPais', '')->assertSet('filtroProvincia', $f['provincia'])
        ->assertSet('filtroGeografias', ['Isla QA'])->assertViewHas('totalRegistrosVista', 8)
        ->assertDispatched('catalogo-estado-url', fn ($evento, $parametros): bool => $parametros['restauracion'] === 3
            && $parametros['estado']['pagina'] === 1 && $parametros['estado']['fc'] === ''
            && $parametros['estado']['fg'] === ['Isla QA'] && $parametros['estado']['fprov'] === $f['provincia']);
});

test('un error de fecha o elevación no borra rangos espaciales válidos de la URL', function (): void {
    $f = qa4NavegacionFixture();
    $espacio = ['flat' => '-1', 'flax' => '0', 'flon' => '-77', 'flox' => '-75'];
    $entrada = ['vista' => 'registros', 'fprov' => $f['provincia'], 'ffd' => 'fecha-imposible'] + $espacio;
    $portal = Livewire::withQueryParams($entrada)->test(PortalCatalogo::class)
        ->assertSet('filtroFechaDesde', '')->assertSet('filtroLatMin', '-1')->assertSet('filtroLatMax', '0')
        ->assertSet('filtroLonMin', '-77')->assertSet('filtroLonMax', '-75')->assertViewHas('totalRegistrosVista', 8);
    $portal->call('restaurarSeleccionUrl', ['vista' => 'registros', 'fprov' => $f['provincia'], 'fed' => '1000', 'feh' => '500'] + $espacio, 4)
        ->assertSet('filtroElevDesde', '1000')->assertSet('filtroElevHasta', '')
        ->assertSet('filtroLatMin', '-1')->assertSet('filtroLatMax', '0')->assertSet('filtroLonMin', '-77')->assertSet('filtroLonMax', '-75');
    $portal->call('restaurarSeleccionUrl', ['vista' => 'registros', 'fprov' => $f['provincia'], 'flat' => '91'] + $espacio, 5)
        ->assertSet('filtroLatMin', '')->assertSet('filtroLatMax', '')->assertSet('filtroLonMin', '-77')->assertSet('filtroLonMax', '-75');
});
