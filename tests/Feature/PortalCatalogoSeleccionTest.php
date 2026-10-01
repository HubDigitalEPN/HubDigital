<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Domain\ValueObjects\FiltrosBusqueda;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalEstadisticas;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;

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

test('coordenadas reservadas o inválidas no entran en el mapa ni en la selección georreferenciada', function (): void {
    $f = seleccionPortalFixture();
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['decimal_latitude_visible' => false]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['decimal_latitude' => 91]);
    $datos = app(PortalEstadisticas::class)->datosParaVista(['filo' => $f['filo']]);
    expect((int) $datos['resumen']['registros'])->toBe(3)
        ->and((int) $datos['resumen']['georreferenciados'])->toBe(1)
        ->and((int) $datos['resumen']['aptos'])->toBe(1)
        ->and(array_sum(array_column($datos['mapa'], 'total')))->toBe(1);
    $pagina = app(EloquentProveedorEspecimenesParaArbol::class)->paginaPublica(FiltrosBusqueda::desde([
        'filtroFiloId' => $f['filo'], 'filtroSoloUbicacion' => '1',
    ]), 1);
    expect($pagina['ids'])->toBe([$f['ids'][2]]);
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
