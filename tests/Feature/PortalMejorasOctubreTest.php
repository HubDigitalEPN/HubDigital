<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\SeleccionPaginaChat;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;

uses(Tests\DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

test('las opciones almacenadas se invalidan al corregir nombres o retirar su publicación y permisos', function (): void {
    $f = coleccionOctubre();
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['preparations' => 'Alcohol', 'biome' => 'Bosque', 'sampling_protocol' => 'Red manual', 'colector' => 'Colector Browser']);
    $opciones = app(\Modules\CatalogoPublico\Infrastructure\Adapters\InventarioOpcionesFiltroAdapter::class);
    expect($opciones->obtenerPreparaciones())->toBe(['Alcohol'])->and($opciones->obtenerBiomas())->toBe(['Bosque'])
        ->and($opciones->obtenerMetodosRecoleccion())->toBe(['red manual'])->and($opciones->obtenerColectores())->toBe(['Colector Browser']);
    // Segunda consulta consume el contenido almacenado antes de corregir datos.
    expect($opciones->obtenerPreparaciones())->toBe(['Alcohol']);
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['preparations' => 'Seco']);
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $f['ids'])->update(['recorded_by_visible' => false, 'sampling_protocol_visible' => false]);
    expect($opciones->obtenerPreparaciones())->toBe(['Seco'])->and($opciones->obtenerMetodosRecoleccion())->toBe([])->and($opciones->obtenerColectores())->toBe([]);
    // La publicación se deriva por trigger del linaje; escribir publicado=false
    // directamente vuelve a calcularlo y no retira un ejemplar con filo válido.
    DB::table('taxonomia.especimenes')->whereIn('id', $f['ids'])->update(['taxon_id' => null]);
    expect(DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $f['ids'])->where('publicado', true)->count())->toBe(0);
    expect($opciones->obtenerPreparaciones())->toBe([])->and($opciones->obtenerBiomas())->toBe([]);
});

function coleccionOctubre(): array
{
    $filos = $familias = $especies = $ids = [];
    foreach (['alfa', 'beta', 'gamma', 'delta'] as $i => $nombre) {
        $filos[$i] = (string) Str::uuid(); $familias[$i] = (string) Str::uuid(); $especies[$i] = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert([
            ['id' => $filos[$i], 'padre_id' => null, 'rango' => 'phylum', 'nombre_cientifico' => 'Octophylum'.chr(65 + $i)],
            ['id' => $familias[$i], 'padre_id' => $filos[$i], 'rango' => 'familia', 'nombre_cientifico' => 'Octidae'.chr(65 + $i)],
            ['id' => $especies[$i], 'padre_id' => $familias[$i], 'rango' => 'especie', 'nombre_cientifico' => 'Octobius '.$nombre],
        ]);
    }
    foreach (range(0, 10) as $i) {
        $grupo = $i < 4 ? $i : ($i === 10 ? 2 : 0);
        $provincia = $i === 10 ? 'Loja' : ['Orellana', 'Pichincha', 'Pichincha', 'Pastaza'][$grupo];
        $localidad = $i === 10 ? 'Lugar Tres' : ['Lugar Uno', 'Lugar Dos', 'Lugar Dos', 'Lugar Cuatro'][$grupo];
        $ids[$i] = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert([
            'id' => $ids[$i], 'taxon_id' => $especies[$grupo], 'codigo_catalogo' => 'QA-OCT-'.($i + 1), 'occurrence_id' => 'QA-OCT-'.($i + 1),
            'fila_origen_excel' => $i + 1, 'state_province' => $provincia, 'localidad' => $localidad, 'locality_name' => $localidad,
            'decimal_latitude' => -.273, 'decimal_longitude' => -79.024, 'fecha_colecta' => '2001-01-10',
        ]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $ids[$i], 'publicado' => true]);
    }
    return compact('filos', 'familias', 'especies', 'ids');
}

test('combina cuatro filos y provincias y localidades con unión dentro de cada filtro y conserva mapa registros e historial', function (): void {
    $f = coleccionOctubre();
    $portal = Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)
        ->set('borradorFiltros.filtroFilos', $f['filos'])
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 11)
        ->set('borradorFiltros.filtroFilos', array_slice($f['filos'], 0, 2))
        ->set('borradorFiltros.filtroProvincias', ['Orellana', 'Pichincha'])
        ->set('borradorFiltros.filtroGeografias', ['Lugar Uno', 'Lugar Dos'])
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 8 && array_sum(array_column($d['mapa'], 'total')) === 8);
    $seleccion = seleccionChatRenderizada($portal);
    expect($seleccion)->toMatchArray(['fphs' => array_slice($f['filos'], 0, 2), 'fprovs' => ['Orellana', 'Pichincha'], 'fg' => ['Lugar Uno', 'Lugar Dos']])
        ->and(SeleccionPaginaChat::desde($seleccion)->filtros->filosSeleccionados())->toHaveCount(2);
    $portal->call('cambiarVista', 'registros')->assertViewHas('totalRegistrosVista', 8)
        ->call('ajustarRegistrosPorPagina', 3)->assertViewHas('registrosVista', fn ($r) => count($r) === 3)
        ->call('restaurarSeleccionUrl', $seleccion + ['vista' => 'mapa'], 1)
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 8);
    $portal->call('retirarCriterio', 'filtroFilos', 0)->assertSet('filtroFilos', [$f['filos'][1]])
        ->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 1);
});

test('1127 ejemplares se buscan por LOV sin generar hojas de registro en el árbol ni revelar códigos reservados', function (): void {
    $f = coleccionOctubre();
    $registros = $visibilidad = [];
    foreach (range(11, 1126) as $i) {
        $id = (string) Str::uuid();
        $registros[] = ['id' => $id, 'taxon_id' => $f['especies'][0], 'codigo_catalogo' => 'QA-OCT-'.($i + 1), 'occurrence_id' => 'QA-OCT-'.($i + 1),
            'fila_origen_excel' => $i + 1, 'decimal_latitude' => -.273, 'decimal_longitude' => -79.024];
        $visibilidad[] = ['id' => (string) Str::uuid(), 'especimen_id' => $id, 'publicado' => true];
    }
    DB::table('taxonomia.especimenes')->insert($registros);
    DB::table('divulgacion.especimenes_divulgables')->insert($visibilidad);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][0])->update(['occurrence_id_visible' => false]);
    $portal = Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class)->call('abrirCelda', -.273, -79.024);
    $detalle = $portal->instance()->detalleCelda;
    expect($detalle['totalUbicacion'])->toBe(1127)->and($detalle['arbolResumido'])->toBeTrue()
        ->and(array_unique(array_column($detalle['arbolVisual'], 'rango')))->toBe(['phylum', 'familia'])
        ->and(count($detalle['registrosLov']))->toBe(30)->and($detalle['lovHayMas'])->toBeTrue();
    $portal->assertDontSee('data-rango="registro"', false)->assertDontSee('1127 registros · mostrar')
        ->set('busquedaRegistroCelda', 'QA-OCT-1127');
    expect(array_column($portal->instance()->detalleCelda['registrosLov'], 'codigo'))->toBe(['QA-OCT-1127']);
    $idUltimo = DB::table('taxonomia.especimenes')->where('occurrence_id', 'QA-OCT-1127')->value('id');
    $portal->call('seleccionarRegistroCelda', $idUltimo)->assertDispatched('registro-celda-seleccionado');
    expect($portal->instance()->detalleCelda['registros'][0]->especimen_id)->toBe($idUltimo);
    $portal->set('busquedaRegistroCelda', 'QA-OCT-1');
    expect(array_column($portal->instance()->detalleCelda['registrosLov'], 'id'))->not->toContain($f['ids'][0]);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][0])->update(['decimal_latitude' => -1]);
    $portal->call('seleccionarRegistroCelda', $f['ids'][0])->assertStatus(404);
});

test('una ubicación de un ejemplar conserva su hoja sin selector ni botones duplicados y el encabezado cambia al ver registros', function (): void {
    $f = coleccionOctubre();
    // Nombre con evidencia congelada: el botón solo se publica cuando existe una referencia.
    DB::table('taxonomia.taxones')->where('id', $f['especies'][2])->update(['nombre_cientifico' => 'Ectatomma ruidum']);
    $portal = Livewire::withQueryParams(['vista' => 'mapa', 'fc' => 'QA-OCT-11'])->test(PortalCatalogo::class)->call('abrirCelda', -.273, -79.024);
    expect($portal->instance()->detalleCelda['arbolResumido'])->toBeFalse()
        ->and(array_filter($portal->instance()->detalleCelda['arbolVisual'], fn ($n) => $n['rango'] === 'registro'))->toHaveCount(1);
    $portal->assertDontSee('Buscar ejemplar <span', false)->assertDontSee('Abrir ficha del registro')
        ->assertDontSee('Autoridad y revisión del nombre')->assertSee('Verificación del nombre científico');
    expect(substr_count($portal->html(), '>Verificación del nombre científico</summary>'))->toBe(1);
    $portal->call('cambiarVistaCelda', 'registros')->assertDontSee('El código permanece visible al desplazarte.')
        ->assertDontSee('Mostrar todos los campos');
    expect(substr_count($portal->html(), '>Registros de esta ubicación</h3>'))->toBe(1);
});

test('el chat responde las cinco preguntas de las capturas con métricas públicas y ganadores diferentes para especies y registros', function (): void {
    coleccionOctubre();
    Http::fake();
    foreach ([
        ['cuantas especies tiene este portal', 4, null],
        ['cual localidad tiene mas especies', 2, 'Lugar Dos'],
        ['cual localidad tiene mas registros', 7, 'Lugar Uno'],
        ['que provincia tiene mas especies ?', 2, 'Pichincha'],
        ['que provincia tiene mas registros', 7, 'Orellana'],
        ['¿En cuál localidad hay más especies?', 2, 'Lugar Dos'],
        ['¿Cuál es la provincia con mayor cantidad de registros?', 7, 'Orellana'],
        ['¿Cuántas especies distintas hay en toda la colección?', 4, null],
    ] as [$pregunta, $cantidad, $ganador]) {
        $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class));
        expect($respuesta['fuente'])->toBe('catalogo')->and($respuesta['total'])->toBe($cantidad)
            ->and($respuesta['datos']['total'])->toBe($cantidad)
            ->and($respuesta['texto'])->not->toContain('Wikipedia', 'Abre Colección', 'Formicidae');
        if ($ganador !== null) expect($respuesta['rows'])->toBe([$ganador]);
    }
    Http::assertNothingSent();
});

test('el ranking del chat conserva empates y la selección solicitada y excluye geografía y nombres reservados', function (): void {
    $f = coleccionOctubre();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $r = $asistente->responder('que provincia tiene mas especies en esta seleccion', $handler, seleccionPortal: ['fphs' => [$f['filos'][1], $f['filos'][3]]]);
    expect($r['rows'])->toBe(['Pastaza', 'Pichincha'])->and($r['total'])->toBe(1)->and($r['texto'])->toContain('cada una', 'selección aplicada');
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', [$f['ids'][1], $f['ids'][2]])->update(['state_province_visible' => false, 'locality_name_visible' => false]);
    $r = $asistente->responder('que provincia tiene mas especies', $handler);
    expect($r['rows'])->not->toContain('Pichincha');
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', [$f['ids'][2], $f['ids'][10]])->update(['scientific_name_visible' => false]);
    expect($asistente->responder('cuantas especies tiene este portal', $handler)['total'])->toBe(3);
});

test('los siete pies de figura tienen ochenta palabras y numeración consecutiva', function (): void {
    $figuras = config('figuras_portal');
    expect(array_column($figuras, 'numero'))->toBe(range(1, 7));
    foreach ($figuras as $figura) expect(preg_split('/\s+/u', trim($figura['texto'])))->toHaveCount(80);
});
