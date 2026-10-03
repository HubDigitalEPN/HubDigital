<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentEspecimenDivulgableRepository;
use Modules\CatalogoPublico\Presentation\Http\Controllers\TablaEspecimenesDivulgados;

uses(Tests\DatabaseFeatureTestCase::class);

function seleccionPortalFixture(): array
{
    $filo = (string) Str::uuid();
    // Los nombres taxonómicos ficticios deben contener letras, no dígitos de códigos.
    $prefijo = 'Select'.Str::lower((string) preg_replace('/[^A-Za-z]/', '', Str::random(24)));
    $taxones = [(string) Str::uuid(), (string) Str::uuid()];
    $localidad = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert(['id' => $filo, 'nombre_cientifico' => $prefijo, 'rango' => 'phylum', 'autor' => 'QA', 'anio_descripcion' => 2026]);
    foreach ($taxones as $i => $id) DB::table('taxonomia.taxones')->insert([
        'id' => $id, 'padre_id' => $filo, 'nombre_cientifico' => $prefijo.' '.($i ? 'beta' : 'alfa'), 'rango' => 'especie', 'autor' => 'QA', 'anio_descripcion' => 2026,
    ]);
    DB::table('taxonomia.localidades')->insert(['id' => $localidad, 'nombre_canonico' => $prefijo, 'rango' => 'sitio']);
    $muestra = (string) Str::uuid();
    DB::table('taxonomia.muestras_colecta')->insert(['id' => $muestra, 'sampling_protocol' => 'Red '.$prefijo]);
    $ids = $codigos = [];
    foreach ([0, 1, 2] as $i) {
        $ids[$i] = (string) Str::uuid();
        $codigos[$i] = 'QA-SELECT-'.Str::upper(Str::random(10));
        DB::table('taxonomia.especimenes')->insert([
            'id' => $ids[$i], 'codigo_catalogo' => $codigos[$i], 'occurrence_id' => $codigos[$i],
            'taxon_id' => $taxones[$i === 2 ? 1 : 0], 'localidad_id' => $localidad,
            'localidad' => $prefijo, 'state_province' => $i === 2 ? 'Napo' : 'Pichincha',
            'fecha_colecta' => $i === 0 ? '1985-06-15' : '2025-01-10',
            'colector' => $i === 0 ? 'Ana '.$prefijo : 'Luis '.$prefijo,
            'preparations' => $i === 0 ? 'Alcohol' : 'Seco', 'muestra_id' => $muestra,
            'decimal_latitude' => $i === 2 ? -2.5 : -0.25,
            'decimal_longitude' => $i === 2 ? -76.25 : -78.5,
            'elevation_min_m' => $i === 0 ? 100 : 900, 'elevation_max_m' => $i === 0 ? 200 : 1000,
            'biome' => $i === 0 ? 'Bosque' : 'Paramo', 'habitat' => $i === 0 ? 'Hojarasca' : 'Suelo',
            'type_status' => $i === 0 ? 'Holotype' : 'Paratype', 'caste' => $i === 0 ? 'Queen' : 'Worker',
            'life_stage' => $i === 0 ? 'Larva' : 'Adult',
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert([
            'id' => (string) Str::uuid(), 'especimen_id' => $ids[$i], 'publicado' => true,
        ]);
    }
    return compact('filo', 'prefijo', 'taxones', 'ids', 'codigos');
}

test('cada filtro conserva la misma selección en tabla, tarjetas y todos los agregados', function (): void {
    $f = seleccionPortalFixture();
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    $estadisticas = app(PortalEstadisticas::class);
    $casos = [
        [[], [], [0, 1, 2]],
        [['filtroCatalogo' => $f['codigos'][0]], ['codigo' => $f['codigos'][0]], [0]],
        [['filtroTaxon' => $f['prefijo'].' beta'], ['taxon' => $f['prefijo'].' beta'], [2]],
        [['filtroProvincia' => 'Pichincha'], ['provincia' => 'Pichincha'], [0, 1]],
        [['filtroGeografias' => [$f['prefijo']]], ['geografias' => [$f['prefijo']]], [0, 1, 2]],
        [['filtroPreparaciones' => ['Alcohol']], ['preparaciones' => ['Alcohol']], [0]],
        [['filtroColector' => 'Ana '.$f['prefijo']], ['colector' => 'Ana '.$f['prefijo']], [0]],
        [['filtroFechaDesde' => '2025-01-01'], ['desde_fecha' => '2025-01-01'], [1, 2]],
        [['filtroFechaHasta' => '1990-01-01'], ['hasta_fecha' => '1990-01-01'], [0]],
        [['filtroMes' => '6'], ['mes' => '6'], [0]],
        [['filtroMetodos' => ['Red '.$f['prefijo']]], ['metodos' => ['Red '.$f['prefijo']]], [0, 1, 2]],
        [['filtroLatMin' => '-1', 'filtroLatMax' => '0'], ['lat_min' => -1, 'lat_max' => 0], [0, 1]],
        [['filtroLonMin' => '-77', 'filtroLonMax' => '-75'], ['lon_min' => -77, 'lon_max' => -75], [2]],
        [['filtroElevDesde' => '500'], ['elev_desde' => 500], [1, 2]],
        [['filtroElevHasta' => '300'], ['elev_hasta' => 300], [0]],
        [['filtroBiomas' => ['Bosque']], ['biomas' => ['Bosque']], [0]],
        [['filtroHabitat' => 'Hojarasca'], ['habitat' => 'Hojarasca'], [0]],
        [['filtroTipo' => 'Holotype'], ['tipo' => 'Holotype'], [0]],
        [['filtroCasta' => 'Queen'], ['casta' => 'Queen'], [0]],
        [['filtroEstadio' => 'Larva'], ['estadio' => 'Larva'], [0]],
        [['filtroIdentificacion' => 'superior'], ['identificacion' => 'superior'], []],
        [['filtroSoloUbicacion' => '1', 'filtroDatosCompletos' => '1'], ['ubicacion' => '1', 'aptitud' => 'completos'], [0, 1, 2]],
        [['filtroProvincia' => 'Pichincha', 'filtroMes' => '1'], ['provincia' => 'Pichincha', 'mes' => '1'], [1]],
    ];
    foreach ($casos as [$entrada, $analisis, $indices]) {
        $filtros = FiltrosBusqueda::desde($entrada + ['filtroFiloId' => $f['filo']]);
        $esperados = array_map(fn ($i) => $f['ids'][$i], $indices);
        $pagina = $repo->paginaPublica($filtros, 1);
        expect($pagina['ids'])->toEqualCanonicalizing($esperados);
        expect(array_sum($repo->resumenRaiz($filtros)['conteos']))->toBe(count($indices));
        $datos = $estadisticas->datosParaVista($analisis + ['filo' => $f['filo']]);
        expect((int) $datos['resumen']['registros'])->toBe(count($indices))
            ->and((int) $datos['resumen']['aptos'])->toBe(count($indices))
            ->and(array_sum($datos['filos']))->toBe(count($indices))
            ->and(array_sum(array_column($datos['mapa'], 'total')))->toBe(count($indices))
            ->and(array_sum(array_column($datos['riqueza'], 'registros')))->toBe(count($indices))
            ->and(array_sum(array_column($datos['decadas'], 'registros')))->toBe(count($indices))
            ->and(array_sum(array_column($datos['especies'], 'total')))->toBe(count($indices))
            ->and(array_sum(array_column($datos['raras'], 'total')))->toBe(count($indices));
    }
});

test('la selección desde un panel actualiza el mapa y limpiar restaura los controles', function (): void {
    $f = seleccionPortalFixture();
    Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)
        ->call('seleccionarFilo', $f['prefijo'])->assertSet('filtroFiloId', $f['filo'])
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 3)
        ->call('seleccionarProvincia', 'Pichincha')->assertSet('filtroProvincia', 'Pichincha')
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 2 && array_sum(array_column($d['mapa'], 'total')) === 2)
        ->call('seleccionarDecada', 1980)->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 1)
        ->call('cambiarVista', 'registros')->assertViewHas('totalRegistrosVista', 1)
        ->assertSee($f['codigos'][0])->assertDontSee($f['codigos'][1])
        ->call('limpiarFiltros')->assertSet('filtroFiloId', '')->assertSet('filtroProvincia', '')->assertSet('filtroFechaDesde', '');
});

test('coordenadas reservadas o inválidas excluyen el registro de todo el portal', function (): void {
    $f = seleccionPortalFixture();
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['decimal_latitude_visible' => false]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['decimal_latitude' => 91]);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect((int) $datos['resumen']['registros'])->toBe(1)
        ->and((int) $datos['resumen']['georreferenciados'])->toBe(1)
        ->and((int) $datos['resumen']['aptos'])->toBe(1)
        ->and(array_sum(array_column($datos['mapa'], 'total')))->toBe(1);
    $pagina = app(EloquentProveedorEspecimenesParaArbol::class)->paginaPublica(FiltrosBusqueda::desde([
        'filtroFiloId' => $f['filo'], 'filtroSoloUbicacion' => '1',
    ]), 1);
    expect($pagina['ids'])->toBe([$f['ids'][2]]);
});

test('el par de coordenadas públicas define la misma población del portal y conserva los excluidos en curaduría', function (): void {
    $f = seleccionPortalFixture();
    $original = (array) DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->first();
    unset($original['coordenadas_otras_regiones'], $original['busqueda_global']);
    $excluidos = $instantaneas = [];
    $casos = [
        [['decimal_latitude' => null, 'decimal_longitude' => null], []],
        [['decimal_latitude' => null], []],
        [['decimal_longitude' => null], []],
        [['decimal_latitude' => 91], []],
        [['decimal_longitude' => -181], []],
        [[], ['decimal_latitude_visible' => false]],
        [[], ['decimal_longitude_visible' => false]],
    ];
    foreach ($casos as $i => [$coordenadas, $visibilidad]) {
        $id = (string) Str::uuid();
        $codigo = $f['codigos'][0].'-EXCLUIDO-'.$i;
        DB::table('taxonomia.especimenes')->insert(array_replace($original, [
            'id' => $id, 'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo,
        ], $coordenadas));
        DB::table('divulgacion.especimenes_divulgables')->insert(array_replace([
            'id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true,
        ], $visibilidad));
        $excluidos[$id] = $codigo;
        $instantaneas[$id] = (array) DB::table('taxonomia.especimenes')->where('id', $id)->first();
    }
    $filtros = FiltrosBusqueda::desde(['filtroFiloId' => $f['filo']]);
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    expect($repo->paginaPublica($filtros, 1)['ids'])->toEqualCanonicalizing($f['ids'])
        ->and(array_sum($repo->resumenRaiz($filtros)['conteos']))->toBe(3)
        ->and($repo->cursorParaCsv($filtros)->pluck('occurrence_id')->all())->toEqualCanonicalizing($f['codigos']);
    $publicados = app(EloquentEspecimenDivulgableRepository::class)->buscarPublicadosPorEspecimenIds([...$f['ids'], ...array_keys($excluidos)]);
    expect(array_map(static fn ($registro) => $registro->especimenId(), $publicados))->toEqualCanonicalizing($f['ids']);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']], false);
    expect((int) $datos['resumen']['registros'])->toBe(3)
        ->and(array_sum(array_column($datos['mapa'], 'total')))->toBe(3)
        ->and(array_sum($datos['filos']))->toBe(3);
    $chat = app(ConsultaCatalogoPublico::class);
    expect($chat->responder('¿Cuántos registros de '.$f['prefijo'].' hay?')['datos']['total'])->toBe(3);
    foreach ($excluidos as $codigo) {
        expect($chat->responder('Busca '.$codigo)['datos']['total'])->toBe(0);
    }
    foreach (['tarjetas', 'registros'] as $vista) {
        $componente = Livewire::withQueryParams(['vista' => $vista, 'fph' => $f['filo'], 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa'])
            ->test(PortalCatalogo::class)->assertViewHas('totalRegistrosVista', 2);
        foreach ($excluidos as $codigo) $componente->assertDontSee($codigo);
    }
    $curador = \App\Models\User::factory()->curador()->create();
    $curaduria = Livewire::actingAs($curador)->test(TablaEspecimenesDivulgados::class)
        ->set('busquedaTaxonomia', $f['prefijo'])->set('publicacion', 'curaduria')
        ->assertViewHas('especimenes', fn ($registros) => $registros->total() === 7)
        ->assertSee('Sin par de coordenadas')->assertSee('revisar fuente')->assertSee('Coordenadas reservadas');
    foreach ($excluidos as $codigo) $curaduria->assertSee($codigo);
    $curaduria->set('regionCoordenadas', 'incompletas')->assertViewHas('especimenes', fn ($registros) => $registros->total() === 3);
    $curaduria->set('regionCoordenadas', 'reservadas')->assertViewHas('especimenes', fn ($registros) => $registros->total() === 2);
    $curaduria->set('regionCoordenadas', '')->set('publicacion', 'publicos')
        ->assertViewHas('especimenes', fn ($registros) => $registros->total() === 3);
    foreach ($instantaneas as $id => $antes) {
        expect((array) DB::table('taxonomia.especimenes')->where('id', $id)->first())->toBe($antes)
            ->and(DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->value('publicado'))->toBeTrue();
    }
});

test('el panel inicial calcula agregados sin consultar imágenes ni resolver referencias taxonómicas', function (): void {
    $f = seleccionPortalFixture();
    Http::fake();
    $estadisticas = app(PortalEstadisticas::class);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $inicial = $estadisticas->datosParaVista(['filo' => $f['filo']], false);
        $consultasIniciales = array_column(DB::getQueryLog(), 'query');
        expect(implode("\n", $consultasIniciales))->not->toContain('imagenes_taxonomicas')
            ->and($inicial['mosaico'])->toBe([])->and($inicial['taxon_mosaico'])->toBe([])
            ->and($inicial['ilustraciones_mosaico'])->toBe([])->and($inicial['descripcion_mosaico'])->toBe('')
            ->and((int) $inicial['resumen']['registros'])->toBe(3);
        Http::assertNothingSent();
        DB::flushQueryLog();
        $seleccionado = $estadisticas->datosParaVista(['filo' => $f['filo']], true);
        expect(implode("\n", array_column(DB::getQueryLog(), 'query')))->toContain('imagenes_taxonomicas')
            ->and($seleccionado['resumen'])->toBe($inicial['resumen'])->and($seleccionado['mapa'])->toBe($inicial['mapa']);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

test('las opciones de filo omiten ramas sin material geográfico público e invalidan su caché al reservarlas', function (): void {
    $f = seleccionPortalFixture();
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    expect(array_column($repo->filosPublicosDisponibles(), 'id'))->toContain($f['filo']);
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['decimal_latitude' => null, 'decimal_longitude' => null]);
    expect(array_column($repo->filosPublicosDisponibles(), 'id'))->not->toContain($f['filo']);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['decimal_latitude' => -0.25, 'decimal_longitude' => -78.5]);
    expect(array_column($repo->filosPublicosDisponibles(), 'id'))->toContain($f['filo']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['decimal_longitude_visible' => false]);
    expect(array_column($repo->filosPublicosDisponibles(), 'id'))->not->toContain($f['filo']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['decimal_longitude_visible' => true, 'scientific_name_visible' => false]);
    expect(array_column($repo->filosPublicosDisponibles(), 'id'))->not->toContain($f['filo']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['scientific_name_visible' => true]);
    expect(array_column($repo->filosPublicosDisponibles(), 'id'))->toContain($f['filo']);
});

test('un rango incompleto muestra el error y limpiar permite volver a aplicar', function (): void {
    Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)
        ->set('filtroLatMin', '-1')->call('actualizarFiltros')->assertHasErrors(['filtroLatMax' => 'required_with'])
        ->call('limpiarFiltros')->assertHasNoErrors()->assertSet('filtroLatMin', '')
        ->call('actualizarFiltros')->assertHasNoErrors();
});

test('el chatbot distingue los registros de cualquier rango de las especies distintas', function (): void {
    $f = seleccionPortalFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][2])->update(['taxon_id' => $f['filo']]);
    $chat = app(ConsultaCatalogoPublico::class);
    expect($chat->responder('¿Cuántos registros de '.$f['prefijo'].' hay?')['datos']['total'])->toBe(3)
        ->and($chat->responder('¿Cuántas especies de '.$f['prefijo'].' hay?')['datos']['total'])->toBe(1);
    expect($chat->responder('¿Cuántas especies hay en la colección?')['intent'])->toBe('catalogo.species');
});

test('el árbol del chat permite escribir directamente el código o nombre que solicita', function (): void {
    $f = seleccionPortalFixture();
    $asistente = app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal::class);
    $handler = app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler::class);
    expect($asistente->responder($f['codigos'][0], $handler)['datos']['total'])->toBe(1)
        ->and($asistente->responder(mb_strtolower($f['prefijo'].' alfa'), $handler)['datos']['total'])->toBe(2);
});

test('otras regiones se ocultan de toda selección pública y la marca se recalcula al corregir coordenadas', function (): void {
    $f = seleccionPortalFixture();
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['decimal_latitude' => 40, 'decimal_longitude' => -3]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['decimal_latitude' => -0.75, 'decimal_longitude' => -90.3]);
    expect(DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->value('coordenadas_otras_regiones'))->toBeTrue();
    $repo = app(EloquentProveedorEspecimenesParaArbol::class);
    $filtros = FiltrosBusqueda::desde(['filtroFiloId' => $f['filo']]);
    expect($repo->paginaPublica($filtros, 1)['ids'])->toEqualCanonicalizing([$f['ids'][1], $f['ids'][2]])
        ->and($repo->cursorParaCsv($filtros)->pluck('occurrence_id')->all())->not->toContain($f['codigos'][0])
        ->and(app(ConsultaCatalogoPublico::class)->responder('Busca '.$f['codigos'][0])['datos']['total'])->toBe(0);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect(array_sum(array_column($datos['mapa'], 'total')))->toBe(2);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['decimal_latitude' => -0.25, 'decimal_longitude' => -78.5]);
    expect($repo->paginaPublica($filtros, 1)['total'])->toBe(3);
});

test('los marcadores curatoriales y fechas implausibles conservan el registro sin inflar métricas', function (): void {
    $f = seleccionPortalFixture();
    DB::table('taxonomia.taxones')->where('id', $f['taxones'][0])->update(['nombre_cientifico' => 'dañada dañada']);
    DB::table('taxonomia.especimenes')->whereIn('id', [$f['ids'][0], $f['ids'][1]])->update(['state_province' => 'dañada', 'fecha_colecta' => '0190-01-08']);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect((int) $datos['resumen']['registros'])->toBe(3)
        ->and((int) $datos['resumen']['identificados'])->toBe(1)
        ->and((int) $datos['resumen']['fechados'])->toBe(1)
        ->and(array_column($datos['riqueza'], 'provincia'))->toBe(['Napo'])
        ->and(array_column($datos['decadas'], 'decada'))->not->toContain(190);
    Livewire::withQueryParams(['vista' => 'registros', 'fc' => $f['codigos'][0]])->test(PortalCatalogo::class)
        ->assertSee($f['codigos'][0])->assertSee('dañada dañada')->assertSee('0190-01-08')
        ->assertSee('Fecha original pendiente de revisión')
        ->assertSee('no se interpreta como una fecha de colecta confirmada');
});

test('la jerarquía es visible y removible en mapa y su CSV conserva exactamente los mismos registros', function (): void {
    $f = seleccionPortalFixture();
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'nivel' => 'species', 'taxon' => $f['prefijo'].' alfa'])
        ->test(PortalCatalogo::class)->assertSee($f['prefijo'].' alfa')
        ->assertViewHas('hayFiltrosActivos', true)->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 2)
        ->call('cambiarVista', 'registros')->assertSee('Descargar resultados CSV');
    $csv = $componente->instance()->descargarResultados(app(EloquentProveedorEspecimenesParaArbol::class));
    ob_start(); $csv->sendContent(); $contenido = ob_get_clean();
    expect($contenido)->toContain($f['codigos'][0], $f['codigos'][1])->not->toContain($f['codigos'][2]);
    $componente->call('limpiarFiltros')->assertSet('nivel', '')->assertSet('taxon', '')->assertViewHas('hayFiltrosActivos', false);
});

test('un borrador inválido conserva la selección aplicada hasta que el visitante corrige los límites', function (): void {
    $f = seleccionPortalFixture();
    Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->set('borradorFiltros.filtroFechaDesde', '2025-01-01')->set('borradorFiltros.filtroFechaHasta', '2000-01-01')
        ->call('aplicarBorrador')->assertHasErrors(['filtroFechaHasta'])
        ->assertSet('filtroFechaDesde', '')->assertSet('filtroFechaHasta', '')
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 3)
        ->set('borradorFiltros.filtroFechaHasta', '2025-12-31')->call('aplicarBorrador')->assertHasNoErrors()
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 2);
});

test('cada indicador filtra conservando la vista del mapa', function (): void {
    $f = seleccionPortalFixture();
    $casos = [
        ['seleccionarFilo', [$f['prefijo']], 3], ['seleccionarProvincia', ['Pichincha'], 2],
        ['seleccionarDecada', [1980], 1], ['seleccionarMes', [6], 1],
        ['seleccionarAltitud', [0, 499], 1], ['seleccionarMetodo', ['Red '.$f['prefijo']], 3],
    ];
    foreach ($casos as [$accion, $parametros, $cantidad]) {
        Livewire::withQueryParams(['vista' => 'mapa', 'ft' => $f['prefijo']])->test(PortalCatalogo::class)
            ->call($accion, ...$parametros)->assertSet('vista', 'mapa')
            ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === $cantidad && array_sum(array_column($d['mapa'], 'total')) === $cantidad);
    }
});

test('el detalle de cuadrícula recorre grupos y pagina seis registros respetando sus campos reservados', function (): void {
    $f = seleccionPortalFixture();
    $original = (array) DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->first();
    unset($original['coordenadas_otras_regiones'], $original['busqueda_global']);
    for ($i = 0; $i < 13; $i++) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert(array_replace($original, ['id' => $id, 'occurrence_id' => $f['codigos'][0].'-'.$i, 'codigo_catalogo' => $f['codigos'][0].'-'.$i]));
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true]);
    }
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['colector' => 'COLECTOR-RESERVADO-QA']);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['recorded_by_visible' => false]);
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('abrirCelda', -0.25, -78.5)->assertDispatched('abrir-detalle-celda')->assertSet('vistaCelda', 'grupos');
    expect($componente->instance()->detalleCelda['total'])->toBe(15);
    $componente->call('navegarCelda', $f['filo'])->call('navegarCelda', $f['taxones'][0])->assertDontSee('COLECTOR-RESERVADO-QA');
    expect($componente->instance()->detalleCelda['registros'])->toHaveCount(6);
    $primera = array_column($componente->instance()->detalleCelda['registros'], 'occurrence_id');
    $componente->call('paginarCelda', 2)->assertSet('paginaCelda', 2);
    $segunda = array_column($componente->instance()->detalleCelda['registros'], 'occurrence_id');
    expect($segunda)->toHaveCount(6)->and(array_intersect($primera, $segunda))->toBe([]);
    $componente->call('paginarCelda', 3)->assertSet('paginaCelda', 3);
    $tercera = array_column($componente->instance()->detalleCelda['registros'], 'occurrence_id');
    expect($tercera)->toHaveCount(3)->and(array_intersect([...$primera, ...$segunda], $tercera))->toBe([])
        ->and(count(array_unique([...$primera, ...$segunda, ...$tercera])))->toBe(15);
    $componente->call('volverCelda', 0)->call('cambiarVistaCelda', 'registros')->assertSet('paginaCelda', 1)
        ->call('cerrarCelda')->assertSet('celdaMapa', null);
});

test('el chatbot aplica códigos en frases y listas y conserva la consulta en el enlace', function (): void {
    $f = seleccionPortalFixture();
    $chat = app(ConsultaCatalogoPublico::class);
    foreach (['Busca el espécimen '.$f['codigos'][0], 'Busca '.strtolower($f['codigos'][0]), $f['codigos'][0].', '.$f['codigos'][1]] as $i => $pregunta) {
        $respuesta = $chat->responder($pregunta);
        expect($respuesta['datos']['total'])->toBe($i === 2 ? 2 : 1);
        parse_str(parse_url($respuesta['opciones'][0]['url'], PHP_URL_QUERY), $query);
        expect($query['fc'])->toBe($respuesta['entidades']['codigo']);
        $this->get($respuesta['opciones'][0]['url'])->assertOk()->assertSee($f['codigos'][0]);
    }
    expect($chat->responder('Busca MEPN-INV-999999999')['datos']['total'])->toBe(0);
});

test('el chat comparte mes localidad fechas e identificación con el portal y conserva seguimiento selectivo', function (): void {
    $f = seleccionPortalFixture();
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['locality_name' => 'Playa de Oro']);
    $chat = app(ConsultaCatalogoPublico::class);
    $respuesta = $chat->responder('Busca registros de '.$f['prefijo'].' alfa en Pichincha, Playa de Oro, durante junio de 1985, con coordenadas públicas e identificación a especie');
    expect($respuesta['datos']['total'])->toBe(1)
        ->and($respuesta['entidades'])->toMatchArray(['mes' => '6', 'localidad' => 'Playa de Oro', 'desde' => '1985-06-01', 'hasta' => '1985-06-30', 'identificacion' => 'especie', 'ubicacion' => '1']);
    $this->get($respuesta['opciones'][0]['url'])->assertOk()->assertSee($f['codigos'][0])->assertDontSee($f['codigos'][1]);
    $seguimiento = $chat->responder('¿Y solo los que tienen coordenadas públicas?', ['taxon' => $f['prefijo'].' alfa', 'mes' => '6']);
    expect($seguimiento['datos']['total'])->toBe(1)->and($seguimiento['entidades']['taxon'])->toBe($f['prefijo'].' alfa');
    $cambio = $chat->responder('No busco mariposas, busco escarabajos de Esmeraldas.', ['taxon' => $f['prefijo'].' alfa']);
    expect($cambio['entidades']['taxon'])->toBe('Coleoptera');
});

test('la ayuda explica los filtros solicitados y mantiene las preguntas del portal fuera de fuentes generales', function (): void {
    $f = seleccionPortalFixture();
    $asistente = app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal::class);
    $handler = app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler::class);
    $respuesta = $asistente->responder('Dame los pasos para filtrar por taxón '.$f['prefijo'].' alfa, provincia Pichincha y mes enero.', $handler);
    expect($respuesta['intent'])->toBe('portal.filtros')
        ->and($respuesta['texto'])->toContain($f['prefijo'].' alfa', 'Pichincha', 'Mes de colecta = 1', 'Tarjetas', 'Indicador');
    foreach (['como vusco espesimenes en el catalgo', '¿Dónde puedo consultar los ejemplares de la colección?'] as $pregunta) {
        expect($asistente->responder($pregunta, $handler)['intent'])->toBe('portal.filtros');
    }
    $mapa = $asistente->responder('¿Por qué no aparecen puntos en el mapa?', $handler);
    expect($mapa['intent'])->toBe('portal.mapa_ayuda')
        ->and($mapa['texto'])->toContain('latitud y longitud públicas y válidas', 'filtros', 'URL');
    expect($asistente->responder('¿Qué es la guía de movilización?', $handler)['intent'])->toBe('documentos_permisos')
        ->and($asistente->responder('¿Son registros o especies distintas?', $handler)['intent'])->toBe('portal.conteos');
});

test('el modal asocia las fotos a su registro y curaduría permite filtrar los registros ocultos por región', function (): void {
    $f = seleccionPortalFixture();
    DB::table('divulgacion.imagenes_taxonomicas')->insert([
        'id' => (string) Str::uuid(), 'occurrence_id' => $f['codigos'][0],
        'ruta' => 'divulgacion/imagenes/qa-celda-'.Str::uuid().'.jpg', 'disco' => 'r2',
        'nombre_original' => 'ejemplar.jpg', 'autor_nombre' => 'QA', 'autor_apellido' => 'Portal', 'autor_nombre_completo' => 'QA Portal',
    ]);
    $componente = Livewire::withQueryParams(['vista' => 'mapa', 'fph' => $f['filo']])->test(PortalCatalogo::class)
        ->call('abrirCelda', -0.25, -78.5)->call('cambiarVistaCelda', 'registros')->assertSee('ejemplar.jpg');
    expect(array_keys($componente->instance()->detalleCelda['imagenes']))->toBe([$f['codigos'][0]]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['decimal_latitude' => 40.4, 'decimal_longitude' => -3.7]);
    Livewire::actingAs(\App\Models\User::factory()->curador()->create())
        ->test(\Modules\CatalogoPublico\Presentation\Http\Controllers\TablaEspecimenesDivulgados::class)
        ->set('busquedaTaxonomia', $f['prefijo'])->set('regionCoordenadas', 'otras')
        ->assertSee($f['codigos'][0])->assertDontSee($f['codigos'][1])->assertDontSee($f['codigos'][2])
        ->assertSee('Coordenadas de otras regiones')->assertSee('Solo curaduría');
});
