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

test('las explicaciones se reservan al indicador y al PDF y el panel de filtros ofrece provincias por LOV sin coordenadas', function (): void {
    coleccionOctubre();
    $portal = Livewire::withQueryParams(['vista' => 'mapa'])->test(PortalCatalogo::class);
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$portal->html());
    $dom = new DOMXPath($documento);
    expect($dom->query('//template[contains(@class,"atlas-export-caption")]')->length)->toBe(7)
        ->and($dom->query('//p[contains(@class,"atlas-figure-caption") and not(ancestor::template)]')->length)->toBe(0)
        ->and($dom->query('//dialog[contains(@class,"atlas-index-dialog")]//p[@class="atlas-indicator-explanation"]')->length)->toBe(7)
        ->and($dom->query('//*[@id="menu-filos"]//button[contains(.,"Alternar tipo")]')->length)->toBe(0)
        ->and($dom->query('//*[@id="menu-mapa"]//button[normalize-space(.)="Exportar a PDF"]')->length)->toBe(1)
        ->and($dom->query('//button[@*[name()="x-on:click" and .="abrirProvincias()"]]')->length)->toBe(1)
        ->and($dom->query('//button[contains(@class,"research-locality-apply")]')->length)->toBe(1)
        ->and($dom->query('//input[@*[starts-with(name(),"wire:model") and (contains(.,"filtroLatitud") or contains(.,"filtroLongitud"))]]')->length)->toBe(0);
    $portal->assertDontSee('Los filtros se aplican al cambiar un dato');
});

test('los saludos y definiciones no heredan la respuesta anterior ni se confunden con búsquedas enciclopédicas', function (): void {
    coleccionOctubre(); Http::fake();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    foreach (['ola', 'hola', '¡Hola!', 'buenas tardes'] as $pregunta) {
        $respuesta = $asistente->responder($pregunta, $handler);
        expect($respuesta['intent'])->toBe('saludo')->and($respuesta['texto'])->not->toContain('Creo que', 'Wikipedia', 'OLA');
    }
    foreach ([
        ['que es esto ?', 'portal.presentacion', 'Colección Biológica'],
        ['que es una coleccion biologica', 'portal.definicion_coleccion', 'ejemplares'],
        ['que es una hormiga ?', 'biologia.hormig', 'insectos sociales'],
        ['quien eres tu', 'conversacion.quien eres', 'asistente del portal'],
        ['qué es Nematomorpha', 'biologia.nematomorf', 'gusanos'],
    ] as [$pregunta, $intent, $texto]) {
        $respuesta = $asistente->responder($pregunta, $handler, contextoCatalogo: ['intent' => 'saludo', 'taxon' => 'Annelida']);
        expect($respuesta['intent'])->toBe($intent)->and($respuesta['texto'])->toContain($texto)->not->toContain('No encontré una fuente', 'Abre Colección');
    }
    $respuesta = $asistente->responder('cuantas ubicaciones tenemos', $handler, contextoCatalogo: ['intent' => 'saludo']);
    expect($respuesta['intent'])->toBe('catalogo.ubicaciones')->and($respuesta['total'])->toBe(1)->and($respuesta['texto'])->not->toContain('¡Hola!');
    Http::assertNothingSent();
});

test('Annelida con cuatro ejemplares sin identificar a especie explica ambos conteos sin inventar especies', function (): void {
    $f = coleccionOctubre();
    DB::table('taxonomia.taxones')->where('id', $f['filos'][0])->update(['nombre_cientifico' => 'Annelida']);
    DB::table('taxonomia.especimenes')->whereIn('id', [$f['ids'][0], $f['ids'][4], $f['ids'][5], $f['ids'][6]])->update(['taxon_id' => $f['familias'][0]]);
    DB::table('taxonomia.especimenes')->whereIn('id', [$f['ids'][7], $f['ids'][8], $f['ids'][9]])->update(['taxon_id' => $f['especies'][1]]);
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $directa = $asistente->responder('cuantas especies hay en el Filo Annelida', $handler);
    expect($directa['fuente'])->toBe('catalogo')->and($directa['datos']['total'])->toBe(0)->and($directa['texto'])->toContain('4 registros publicados', 'nivel de especie');
    $seleccion = $asistente->responder('cuantas especies hay en esta seleccion', $handler, seleccionPortal: ['fphs' => [$f['filos'][0]]]);
    expect($seleccion['datos']['total'])->toBe(0)->and($seleccion['texto'])->toContain('4 registros publicados', 'nivel de especie');
    $mapa = Livewire::withQueryParams(['vista' => 'mapa', 'fphs' => [$f['filos'][0]]])->test(PortalCatalogo::class);
    $mapa->assertViewHas('datosMapa', fn ($d) => (int) $d['resumen']['registros'] === 4);
});

test('el chat cuenta ubicaciones y nombres geográficos públicos distintos respetando filtros y reservas', function (): void {
    $f = coleccionOctubre(); $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    DB::table('taxonomia.especimenes')->where('id', $f['ids'][1])->update(['decimal_latitude' => -.274]);
    foreach ([['ubicaciones', 2], ['localidades', 4], ['provincias', 4]] as [$unidad, $cantidad]) {
        expect($asistente->responder('cuantas '.$unidad.' tenemos', $handler)['total'])->toBe($cantidad);
    }
    expect($asistente->responder('cuantas ubicaciones tenemos', $handler, seleccionPortal: ['fphs' => [$f['filos'][1]]])['total'])->toBe(1);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][1])->update(['decimal_latitude_visible' => false]);
    expect($asistente->responder('cuantas ubicaciones tenemos', $handler)['total'])->toBe(1);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $f['ids'][3])->update(['state_province_visible' => false, 'locality_name_visible' => false]);
    expect($asistente->responder('cuantas provincias tenemos', $handler)['total'])->toBe(3)
        ->and($asistente->responder('cuantas localidades tenemos', $handler)['total'])->toBe(3);
});

test('la conversación real pasa de saludo a métricas y definiciones sin repetir el turno anterior', function (): void {
    coleccionOctubre(); Http::fake();
    $chat = Livewire::test(\Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget::class)->call('nuevaConversacion');
    foreach ([
        ['hola', 'Hola'], ['cuantas ubicaciones tenemos', 'Hay 1 ubicación'],
        ['que es una hormiga ?', 'insectos sociales'], ['ola', 'Hola'],
        ['que provincia tiene mas especies', 'Pichincha'], ['quien eres tu', 'asistente del portal'],
    ] as [$pregunta, $texto]) {
        $chat->set('pregunta', $pregunta)->call('enviar');
        $mensajes = $chat->get('mensajes'); $ultimo = $mensajes[array_key_last($mensajes)];
        expect($ultimo['texto'])->toContain($texto)->not->toContain('Creo que preguntas', 'Ocurrió un error', 'Wikipedia');
    }
    Http::assertNothingSent();
});

test('el detalle del árbol publica una sola foto de R2 o un espacio sin imagen y excluye otros discos', function (): void {
    $f = coleccionOctubre();
    foreach (['local', 'r2'] as $disco) DB::table('divulgacion.imagenes_taxonomicas')->insert([
        'id' => (string) Str::uuid(), 'occurrence_id' => 'QA-OCT-11', 'ruta' => 'divulgacion/imagenes/'.$disco.'-'.Str::uuid().'.jpg',
        'disco' => $disco, 'nombre_original' => 'referencia-'.$disco.'.jpg', 'autor_nombre' => 'QA', 'autor_apellido' => 'Portal', 'autor_nombre_completo' => 'QA Portal',
    ]);
    $portal = Livewire::withQueryParams(['vista' => 'mapa', 'fc' => 'QA-OCT-11'])->test(PortalCatalogo::class)->call('abrirCelda', -.273, -79.024);
    expect($portal->instance()->detalleCelda['imagenes']['QA-OCT-11'])->toHaveCount(1);
    $documento = new DOMDocument; @$documento->loadHTML($portal->html()); $dom = new DOMXPath($documento);
    expect($dom->query('//aside[contains(@class,"atlas-taxon-information")]//img')->length)->toBe(1)
        ->and($dom->query('//aside[contains(@class,"atlas-taxon-information")]//img')->item(0)->getAttribute('src'))->toContain('/portal/imagenes/');
    DB::table('divulgacion.imagenes_taxonomicas')->where('occurrence_id', 'QA-OCT-11')->where('disco', 'r2')->delete();
    $portal = Livewire::withQueryParams(['vista' => 'mapa', 'fc' => 'QA-OCT-11'])->test(PortalCatalogo::class)->call('abrirCelda', -.273, -79.024);
    $portal->assertSee('No hay imagen disponible')->assertDontSee('data-fotografia-ejemplar=', false);
});

test('las seis familias de la captura tienen referencias WebP distintas con créditos y sin sustituir otros taxones', function (): void {
    $urls = [];
    foreach (['Aderidae', 'Anobiidae', 'Anthicidae', 'Aphodiidae', 'Apionidae', 'Apioninae'] as $nombre) {
        $foto = \Modules\CatalogoPublico\Application\Services\FotografiaTarjeta::para($nombre, 'family');
        expect($foto['url'])->toEndWith('.webp')->and($foto['fuente'])->toContain('inaturalist.org/observations/');
        $urls[] = $foto['url'];
        expect(filesize(public_path($foto['url'])))->toBeLessThan(40000);
        $html = view('catalogopublico::components.fotografia-tarjeta', ['nombre' => $nombre, 'nivel' => 'family'])->render();
        expect($html)->toContain($foto['url'], $foto['species'], $foto['licencia_url']);
    }
    expect(array_unique($urls))->toHaveCount(6)
        ->and(\Modules\CatalogoPublico\Application\Services\FotografiaTarjeta::para('Taxon sin referencia', 'family'))->toBeNull()
        ->and(\Modules\CatalogoPublico\Application\Services\FotografiaTarjeta::para('Apionidae', 'genus'))->toBeNull();
});

test('las seis tarjetas de Coleoptera publican imágenes diferentes en la navegación del catálogo', function (): void {
    $f = coleccionOctubre(); $clase = (string) Str::uuid(); $orden = (string) Str::uuid();
    DB::table('taxonomia.taxones')->where('id', $f['filos'][0])->update(['nombre_cientifico' => 'Arthropoda']);
    DB::table('taxonomia.taxones')->insert([
        ['id' => $clase, 'padre_id' => $f['filos'][0], 'rango' => 'clase', 'nombre_cientifico' => 'Insecta'],
        ['id' => $orden, 'padre_id' => $clase, 'rango' => 'orden', 'nombre_cientifico' => 'Coleoptera'],
    ]);
    $nombres = ['Aderidae', 'Anobiidae', 'Anthicidae', 'Aphodiidae', 'Apionidae', 'Apioninae'];
    foreach ($nombres as $i => $nombre) {
        if ($i < 4) {
            DB::table('taxonomia.taxones')->where('id', $f['familias'][$i])->update(['padre_id' => $orden, 'nombre_cientifico' => $nombre]);
            continue;
        }
        $familia = (string) Str::uuid(); $ejemplar = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $familia, 'padre_id' => $orden, 'rango' => 'familia', 'nombre_cientifico' => $nombre]);
        DB::table('taxonomia.especimenes')->insert(['id' => $ejemplar, 'taxon_id' => $familia, 'codigo_catalogo' => 'QA-FOTO-'.$i, 'occurrence_id' => 'QA-FOTO-'.$i, 'fila_origen_excel' => 100 + $i, 'decimal_latitude' => -.273, 'decimal_longitude' => -79.024]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $ejemplar, 'publicado' => true]);
    }
    $portal = Livewire::withQueryParams(['nivel' => 'order', 'taxon' => 'Coleoptera', 'vista' => 'tarjetas'])->test(PortalCatalogo::class);
    $documento = new DOMDocument; @$documento->loadHTML($portal->html()); $dom = new DOMXPath($documento); $urls = [];
    foreach ($nombres as $nombre) {
        $imagenes = $dom->query('//div[@data-foto-tarjeta="'.$nombre.'"]//img');
        expect($imagenes->length)->toBe(1);
        $urls[] = $imagenes->item(0)->getAttribute('src');
    }
    expect(array_unique($urls))->toHaveCount(6);
});

test('la biblioteca de tarjetas no repite fotografías y distingue la única representación sin familia confirmada', function (): void {
    $fuentes = require base_path('Modules/CatalogoPublico/app/Application/Services/FotografiasTarjetas.php');
    expect($fuentes)->toHaveCount(86)->and(array_unique(array_column($fuentes, 'foto')))->toHaveCount(86);
    foreach ($fuentes as $foto) {
        expect(is_file(public_path('images/taxonomia/tarjetas/'.$foto['archivo'].'.webp')))->toBeTrue()
            ->and($foto['licencia'])->toBeIn(['cc0', 'cc-by', 'cc-by-sa']);
    }
    $ilustracion = \Modules\CatalogoPublico\Application\Services\FotografiaTarjeta::para('Melaryidae', 'family');
    expect($ilustracion['ilustracion'])->toBeTrue()->and($ilustracion['species'])->toBe('Coleoptera')
        ->and($ilustracion['fuente'])->toBeNull()
        ->and(\Modules\CatalogoPublico\Application\Services\FotografiaTarjeta::para('Aderidae', 'family', ['phylum' => 'Mollusca']))->toBeNull();
});
