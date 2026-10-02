<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;

uses(Tests\DatabaseFeatureTestCase::class);

function cartografiaRealFixture(): array
{
    $filo = (string) Str::uuid();
    $prefijo = 'Carto'.Str::lower((string) preg_replace('/[^A-Za-z]/', '', Str::random(24)));
    $taxones = [(string) Str::uuid(), (string) Str::uuid()];
    DB::table('taxonomia.taxones')->insert(['id' => $filo, 'nombre_cientifico' => $prefijo, 'rango' => 'phylum', 'autor' => 'QA', 'anio_descripcion' => 2026]);
    foreach ($taxones as $i => $id) DB::table('taxonomia.taxones')->insert([
        'id' => $id, 'padre_id' => $filo, 'nombre_cientifico' => $prefijo.' '.($i ? 'beta' : 'alfa'), 'rango' => 'especie', 'autor' => 'QA', 'anio_descripcion' => 2026,
    ]);
    $muestra = (string) Str::uuid();
    DB::table('taxonomia.muestras_colecta')->insert(['id' => $muestra, 'sampling_protocol' => 'Red '.$prefijo]);
    $ids = $codigos = [];
    foreach ([0, 1, 2] as $i) {
        $ids[$i] = (string) Str::uuid();
        $codigos[$i] = 'QA-CARTO-'.Str::upper(Str::random(10));
        DB::table('taxonomia.especimenes')->insert([
            'id' => $ids[$i], 'codigo_catalogo' => $codigos[$i], 'occurrence_id' => $codigos[$i],
            'taxon_id' => $taxones[$i === 2 ? 1 : 0], 'muestra_id' => $muestra,
            'localidad' => $prefijo, 'state_province' => $i === 2 ? 'Napo' : 'Pichincha',
            'fecha_colecta' => '2025-01-10', 'colector' => 'QA',
            'decimal_latitude' => $i === 2 ? -2.5 : -0.25,
            'decimal_longitude' => $i === 2 ? -76.25 : -78.5,
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $ids[$i], 'publicado' => true]);
    }
    return compact('filo', 'prefijo', 'taxones', 'ids', 'codigos', 'muestra');
}

test('QA3-001 las tarjetas usan UUID y excluyen otro taxón con el mismo código público', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['occurrence_id' => $f['codigos'][0]]);
    $componente = Livewire::withQueryParams(['vista' => 'tarjetas', 'fph' => $f['filo'], 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa'])
        ->test(PortalCatalogo::class)->assertViewHas('totalEspecimenes', 2)
        ->assertViewHas('especimenes', fn ($filas) => count($filas) === 2 && array_diff(array_column($filas, 'especimen_id'), [$f['ids'][0], $f['ids'][1]]) === []);
    $componente->call('cambiarVista', 'registros')->assertViewHas('totalRegistrosVista', 2)
        ->assertViewHas('registrosVista', fn ($filas) => array_column($filas, 'scientific_name') === [$f['prefijo'].' alfa', $f['prefijo'].' alfa']);
    $componente->call('cambiarVista', 'mapa')->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 2);
});

test('tarjetas y tabla cargan doce UUID por página sin duplicarlos ni perder el resto', function (): void {
    $f = cartografiaRealFixture();
    $original = (array) DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->first();
    unset($original['coordenadas_otras_regiones'], $original['busqueda_global']);
    for ($i = 0; $i < 23; $i++) {
        $id = (string) Str::uuid(); $codigo = 'QA-PAGINA-'.Str::uuid();
        DB::table('taxonomia.especimenes')->insert(array_replace($original, ['id' => $id, 'occurrence_id' => $codigo, 'codigo_catalogo' => $codigo]));
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true]);
    }
    foreach (['tarjetas' => 'especimenes', 'registros' => 'registrosVista'] as $vista => $clave) {
        $componente = Livewire::withQueryParams(['vista' => $vista, 'fph' => $f['filo'], 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa'])->test(PortalCatalogo::class)
            ->assertViewHas('totalRegistrosVista', 25)->assertViewHas('ultimaPagina', 3)->assertViewHas($clave, fn ($filas) => count($filas) === 12);
        $primera = array_column($componente->viewData($clave), 'especimen_id');
        $componente->call('cambiarPagina', 2)->assertViewHas($clave, fn ($filas) => count($filas) === 12);
        $segunda = array_column($componente->viewData($clave), 'especimen_id');
        $componente->call('cambiarPagina', 3)->assertViewHas($clave, fn ($filas) => count($filas) === 1);
        expect(array_intersect($primera, $segunda))->toBe([])
            ->and(count(array_unique([...$primera, ...$segunda, ...array_column($componente->viewData($clave), 'especimen_id')])))->toBe(25);
    }
});

test('el mapa conserva coordenadas exactas y un punto vecino no entra en el modal', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['decimal_latitude' => -0.2561234, 'decimal_longitude' => -78.5134567]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['decimal_latitude' => -0.2641234, 'decimal_longitude' => -78.5194567]);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect($datos['mapa'])->toHaveCount(3)->and(array_column($datos['mapa'], 'lat'))->toContain(-0.2561234, -0.2641234);
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('abrirCelda', -0.2561234, -78.5134567)->call('cambiarVistaCelda', 'registros');
    expect($componente->instance()->detalleCelda['total'])->toBe(1)
        ->and(array_column($componente->instance()->detalleCelda['registros'], 'especimen_id'))->toBe([$f['ids'][0]]);
});

test('el árbol conserva ancestros y ramas hermanas y selecciona su información pública', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['taxon_id' => $f['taxones'][1]]);
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('abrirCelda', -0.25, -78.5)->call('navegarCelda', $f['filo'])->call('navegarCelda', $f['taxones'][0]);
    $detalle = $componente->instance()->detalleCelda;
    expect(array_column($detalle['arbol'], 'id'))->toContain($f['filo'], $f['taxones'][0], $f['taxones'][1])
        ->and($detalle['seleccionado']['nombre'])->toBe($f['prefijo'].' alfa')
        ->and($detalle['informacion']['cantidad'])->toBe(1)
        ->and($detalle['seleccionado']['ilustracion']['representativa'])->toBeTrue();
    $componente->call('navegarCelda', $f['taxones'][1]);
    expect($componente->instance()->detalleCelda['seleccionado']['nombre'])->toBe($f['prefijo'].' beta');
});

test('QA3-002 las notas de reubicación se conservan sin contar como especie o provincia', function (): void {
    $f = cartografiaRealFixture(); $nota = 'muestra reubicada dentro de 1269 muestrareubicadadentrode1269';
    DB::table('taxonomia.taxones')->where('id', $f['taxones'][0])->update(['nombre_cientifico' => $nota]);
    DB::table('taxonomia.especimenes')->whereIn('id', [$f['ids'][0], $f['ids'][1]])->update(['state_province' => 'muestra reubicada dentro de 1269']);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect((int) $datos['resumen']['registros'])->toBe(3)->and((int) $datos['resumen']['identificados'])->toBe(1)
        ->and(array_column($datos['riqueza'], 'provincia'))->toBe(['Napo']);
    Livewire::withQueryParams(['vista' => 'registros', 'fc' => $f['codigos'][0]])->test(PortalCatalogo::class)
        ->assertViewHas('registrosVista', fn ($filas) => $filas[0]->scientific_name === $nota && $filas[0]->taxon_en_revision === true);
});

test('el panel usa el protocolo original sin muestra y respeta su marca de visibilidad', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['muestra_id' => null, 'sampling_protocol' => 'Winkler QA']);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['sampling_protocol' => 'pitfall QA']);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['sampling_protocol' => 'METODO-RESERVADO-QA']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][2])->update(['sampling_protocol_visible' => false]);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect(array_column($datos['metodos'], 'metodo'))->toEqualCanonicalizing(['Winkler QA', 'pitfall QA'])
        ->and(array_sum(array_column($datos['metodos'], 'registros')))->toBe(2);
    $ids = app(EloquentProveedorEspecimenesParaArbol::class)->paginaPublica(FiltrosBusqueda::desde(['filtroFiloId' => $f['filo'], 'filtroMetodos' => ['Winkler QA']]), 1)['ids'];
    expect($ids)->toBe([$f['ids'][0]]);
    Livewire::withQueryParams(['vista' => 'registros', 'fc' => $f['codigos'][0]])->test(PortalCatalogo::class)
        ->assertViewHas('registrosVista', fn ($filas) => $filas[0]->sampling_protocol === 'Winkler QA');
});

test('la comparación geográfica une variantes de tilde y preserva los valores originales', function (): void {
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['country' => 'Perú', 'state_province' => 'Manabí', 'locality_name' => 'Yasuní']);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['country' => 'Peru', 'state_province' => 'Manabi', 'locality_name' => 'Yasuni']);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    foreach (['Perú', 'Peru', ' PERÚ '] as $pais) {
        expect($repo->paginaPublica(FiltrosBusqueda::desde(['filtroFiloId' => $f['filo'], 'filtroPais' => $pais, 'filtroProvincia' => 'manabi', 'filtroGeografias' => ['Yasuni']]), 1)['ids'])
            ->toEqualCanonicalizing([$f['ids'][0], $f['ids'][1]]);
    }
    expect(DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->value('country'))->toBe('Perú');
});

test('cambiar divulgación invalida inmediatamente los puntos y estadísticas cacheados', function (): void {
    $f = cartografiaRealFixture(); $estadisticas = app(PortalEstadisticas::class);
    expect(array_sum(array_column($estadisticas->datosParaVista(['filo' => $f['filo']])['mapa'], 'total')))->toBe(3);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['decimal_latitude_visible' => false]);
    expect(array_sum(array_column($estadisticas->datosParaVista(['filo' => $f['filo']])['mapa'], 'total')))->toBe(2)
        ->and(array_sum(array_column($estadisticas->puntosParaMapa(['filo' => $f['filo']]), 'total')))->toBe(2);
});

test('la recuperación exige las tres identidades de fuente y conserva correcciones previas', function (): void {
    // DatabaseFeatureTestCase revierte también estas filas preexistentes al terminar.
    // Se liberan temporalmente las posiciones de origen para no asumir una BD vacía.
    expect(DB::transactionLevel())->toBeGreaterThan(0);
    DB::table('taxonomia.especimenes')->whereIn('fila_origen_excel', [1, 2, 3])
        ->update(['fila_origen_excel' => null]);
    $f = cartografiaRealFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['fila_origen_excel' => 1, 'occurrence_id' => 'MEPN-INV-1', 'old_code' => '560', 'sampling_protocol' => null]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['fila_origen_excel' => 2, 'occurrence_id' => 'MEPN-INV-1', 'old_code' => 'FUENTE-DISTINTA', 'sampling_protocol' => null]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['fila_origen_excel' => 3, 'occurrence_id' => 'MEPN-INV-2', 'old_code' => '561', 'sampling_protocol' => 'Corrección curatorial']);
    $migracion = require base_path('Modules/CatalogoPublico/database/migrations/2026_10_02_000012_restore_original_sampling_protocol.php');
    $migracion->up(); $migracion->up();
    expect(DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->value('sampling_protocol'))->toBe('fogging')
        ->and(DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->value('sampling_protocol'))->toBeNull()
        ->and(DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->value('sampling_protocol'))->toBe('Corrección curatorial');
});

test('una jerarquía parcial muestra sus especies debajo del filo sin inventar rangos intermedios', function (): void {
    $f = cartografiaRealFixture();
    Livewire::withQueryParams(['vista' => 'tarjetas', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('navegar', 'phylum', $f['prefijo'])->assertViewHas('totalTarjetas', 2)
        ->assertSee($f['prefijo'].' alfa')->assertSee($f['prefijo'].' beta')
        ->call('navegar', 'species', $f['prefijo'].' alfa')->assertViewHas('totalEspecimenes', 2);
});
