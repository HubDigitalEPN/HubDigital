<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\DetectorEntidadesChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\EnlaceSeleccionCatalogo;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

function registrosParaContratoChat(): void
{
    $phylum = (string) Str::uuid();
    $genus = (string) Str::uuid();
    $alpha = (string) Str::uuid();
    $beta = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $phylum, 'nombre_cientifico' => 'Filo QA '.substr($phylum, 0, 8), 'rango' => 'phylum', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => null],
        ['id' => $genus, 'nombre_cientifico' => 'Chatobius', 'rango' => 'genero', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $phylum],
        ['id' => $alpha, 'nombre_cientifico' => 'Chatobius alpha', 'rango' => 'especie', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $genus],
        ['id' => $beta, 'nombre_cientifico' => 'Chatobius beta', 'rango' => 'especie', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $genus],
    ]);
    foreach ([
        ['QA3-CHAT-1', $alpha, 'Pichincha', 'Pichincha', 'Peru', '2000-05-01', 1500],
        ['QA3-CHAT-2', $beta, 'Pichincha', 'Provincia de pichincha', 'Perú', '2000-05-05', 2500],
        ['QA3-CHAT-3', $beta, 'Pichincha', 'Quito', 'Peru', '2000-02-08', 1800],
        ['QA3-CHAT-4', $alpha, 'Esmeraldas', 'Playa de oro', 'Ecuador', '2000-05-05', 1200],
    ] as [$codigo, $taxon, $provincia, $localidad, $pais, $fecha, $elevacion]) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert(['id' => $id, 'codigo_catalogo' => $codigo, 'occurrence_id' => $codigo,
            'taxon_id' => $taxon, 'localidad' => $localidad, 'locality_name' => $localidad,
            'state_province' => $provincia, 'country' => $pais, 'fecha_colecta' => $fecha,
            'elevation_min_m' => $elevacion, 'elevation_max_m' => $elevacion, 'colector' => 'Prueba',
            'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id]);
    }
}

function parametrosEnlaceChat(array $respuesta): array
{
    parse_str((string) parse_url($respuesta['opciones'][0]['url'], PHP_URL_QUERY), $parametros);
    return $parametros;
}

function seleccionChatRenderizada(object $catalogo): array
{
    expect(preg_match('/\bdata-catalogo-seleccion="([^"]*)"/u', $catalogo->html(), $atributo))->toBe(1);

    return json_decode(html_entity_decode($atributo[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true, flags: JSON_THROW_ON_ERROR);
}

function jerarquiaGenerosChat(): array
{
    registrosParaContratoChat();
    $genero = DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Chatobius')->first();
    $reino = (string) Str::uuid(); $clase = (string) Str::uuid(); $orden = (string) Str::uuid(); $familia = (string) Str::uuid();
    $otroGenero = (string) Str::uuid(); $otraEspecie = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $reino, 'padre_id' => null, 'rango' => 'reino', 'nombre_cientifico' => 'Chatanimalia'],
        ['id' => $clase, 'padre_id' => $genero->padre_id, 'rango' => 'clase', 'nombre_cientifico' => 'Chatinsecta'],
        ['id' => $orden, 'padre_id' => $clase, 'rango' => 'orden', 'nombre_cientifico' => 'Chathymenoptera'],
        ['id' => $familia, 'padre_id' => $orden, 'rango' => 'familia', 'nombre_cientifico' => 'Chatformicidae'],
        ['id' => $otroGenero, 'padre_id' => $familia, 'rango' => 'genero', 'nombre_cientifico' => 'Chatoterus'],
        ['id' => $otraEspecie, 'padre_id' => $otroGenero, 'rango' => 'especie', 'nombre_cientifico' => 'Chatoterus gamma'],
    ]);
    DB::table('taxonomia.taxones')->where('id', $genero->padre_id)->update(['nombre_cientifico' => 'Chatarthropoda', 'padre_id' => $reino]);
    DB::table('taxonomia.taxones')->where('id', $genero->id)->update(['padre_id' => $familia]);
    DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-4')->update(['taxon_id' => $otraEspecie]);

    return ['familia' => $familia];
}

test('consultar una provincia no añade una localidad homónima ni una nota con provincia de', function (): void {
    registrosParaContratoChat();
    foreach (['¿Cuántas especies distintas hay en Pichincha?', '¿Cuántas especies distintas hay en la provincia de Pichincha?'] as $pregunta) {
        expect(app(DetectorEntidadesChat::class)->extraer($pregunta))->toBe(['provincia' => 'Pichincha']);
    }
    expect(app(DetectorEntidadesChat::class)->extraer('Busca Chatobius en la localidad de Pichincha'))
        ->toMatchArray(['taxon' => 'Chatobius', 'localidad' => 'Pichincha'])->not->toHaveKey('provincia');
    foreach (['¿Cuántas especies distintas de Chatobius hay en Pichincha?', '¿Cuántas especies distintas de Chatobius hay en la provincia de Pichincha?', '¿Cuántas especies diferentes de Chatobius hay en Pichincha?'] as $pregunta) {
        $respuesta = app(ConsultaCatalogoPublico::class)->responder($pregunta);
        expect($respuesta['entidades'])->toMatchArray(['taxon' => 'Chatobius', 'provincia' => 'Pichincha'])
            ->not->toHaveKey('localidad')
            ->and($respuesta['datos']['total'])->toBe(2)
            ->and(parametrosEnlaceChat($respuesta))->toMatchArray(['ft' => 'Chatobius', 'fprov' => 'Pichincha'])->not->toHaveKey('fg');
    }
    $sitio = app(ConsultaCatalogoPublico::class)->responder('Busca Chatobius en Esmeraldas en Playa de Oro');
    expect($sitio['entidades'])->toMatchArray(['provincia' => 'Esmeraldas', 'localidad' => 'Playa de oro'])
        ->and($sitio['datos']['total'])->toBe(1);
});

test('las fechas ISO y los días naturales aplican exactamente la selección del enlace', function (): void {
    registrosParaContratoChat();
    $catalogo = app(ConsultaCatalogoPublico::class);
    $mes = $catalogo->responder('Busca Chatobius en Esmeraldas en Playa de Oro desde 2000-05-01 hasta 2000-05-31');
    expect($mes['datos']['total'])->toBe(1)
        ->and(parametrosEnlaceChat($mes))->toMatchArray(['ffd' => '2000-05-01', 'ffh' => '2000-05-31']);
    $dias = $catalogo->responder('Busca Chatobius en Esmeraldas en Playa de Oro del 1 al 4 de mayo de 2000');
    expect($dias['datos']['total'])->toBe(0)
        ->and($dias['entidades']['hasta'])->toBe('2000-05-04')
        ->and(parametrosEnlaceChat($dias)['ffh'])->toBe('2000-05-04');
    $invalida = $catalogo->responder('Busca Chatobius entre 2021-01-01 y 2020-01-01');
    expect($invalida['intent'])->toBe('catalogo.aclaracion')
        ->and($invalida['datos']['total'])->toBeNull()
        ->and($invalida['texto'])->toContain('Desde no puede ser posterior');
});

test('las variantes geográficas con acentos conservan la unión y los valores originales', function (): void {
    registrosParaContratoChat();
    foreach (['Perú', 'Peru', ' PERÚ '] as $pais) {
        $respuesta = app(ConsultaCatalogoPublico::class)->responder('Busca Chatobius en '.$pais);
        expect($respuesta['datos']['total'])->toBe(3)
            ->and($respuesta['entidades']['taxon'])->toBe('Chatobius')
            ->and($respuesta['entidades']['pais'])->toBe('Peru')
            ->and(parametrosEnlaceChat($respuesta)['fpais'])->toBe('Peru');
    }
    expect(DB::table('taxonomia.especimenes')->where('occurrence_id', 'QA3-CHAT-2')->value('country'))->toBe('Perú');
    foreach (['Yasuní', 'Yasuni'] as $indice => $localidad) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert(['id' => $id, 'codigo_catalogo' => 'QA3-CHAT-YASUN-'.($indice + 1),
            'occurrence_id' => 'QA3-CHAT-YASUN-'.($indice + 1), 'taxon_id' => DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Chatobius alpha')->value('id'),
            'localidad' => $localidad, 'locality_name' => $localidad, 'fecha_colecta' => '2000-05-05', 'colector' => 'Prueba',
            'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id]);
    }
    foreach (['Yasuní', 'Yasuni', ' YASUNÍ '] as $localidad) {
        expect(app(ConsultaCatalogoPublico::class)->responder('Busca Chatobius en '.$localidad)['datos']['total'])->toBe(2);
    }
});

test('la elevación filtra resultados y enlaza los mismos límites', function (): void {
    registrosParaContratoChat();
    $respuesta = app(ConsultaCatalogoPublico::class)->responder('Busca Chatobius entre 1000 y 2000 metros de altitud');
    expect($respuesta['datos']['total'])->toBe(3)
        ->and($respuesta['entidades'])->toMatchArray(['elev_desde' => '1000', 'elev_hasta' => '2000'])
        ->and(parametrosEnlaceChat($respuesta))->toMatchArray(['fed' => '1000', 'feh' => '2000'])
        ->and($respuesta['texto'])->not->toContain('QA3-CHAT-2');
});

test('las alternativas y los calificadores no soportados no producen conteos parciales', function (string $pregunta, string $limite): void {
    registrosParaContratoChat();
    $respuesta = app(ConsultaCatalogoPublico::class)->responder($pregunta);
    expect($respuesta['intent'])->toBe('catalogo.aclaracion')
        ->and($respuesta['datos']['total'])->toBeNull()
        ->and($respuesta['texto'])->toContain($limite)->toContain('No he calculado un conteo parcial');
})->with([
    ['Busca Annelida o Mollusca', 'alternativas'],
    ['Busca Chatobius en Esmeraldas o Pichincha', 'alternativas'],
    ['Busca Chatobius colectados en enero o febrero', 'alternativas'],
    ['Busca solo hembras de Chatobius', 'sexo'],
    ['Busca Chatobius preservados en alcohol', 'preservación'],
]);

test('los ordinales conservan el orden de la lista y los seguimientos añaden y quitan un mes', function (): void {
    registrosParaContratoChat();
    $catalogo = app(ConsultaCatalogoPublico::class);
    $lista = $catalogo->responder('Busca QA3-CHAT-1 y QA3-CHAT-2');
    $segundo = $catalogo->responder('Dame solo el segundo de esos dos registros.', $lista['entidades']);
    expect($segundo['datos']['total'])->toBe(1)
        ->and($segundo['entidades']['codigo'])->toBe('QA3-CHAT-2')
        ->and($segundo['texto'])->toContain('QA3-CHAT-2')->not->toContain('QA3-CHAT-1');
    $enero = $catalogo->responder('Solo los colectados en enero.', ['taxon' => 'Chatobius', 'provincia' => 'Pichincha']);
    expect($enero['datos']['total'])->toBe(0)
        ->and($enero['entidades'])->toMatchArray(['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'mes' => '1']);
    $febrero = $catalogo->responder('¿Y en febrero?', $enero['entidades']);
    expect($febrero['datos']['total'])->toBe(1);
    $sinMes = $catalogo->responder('¿Y sin filtro de mes?', $febrero['entidades']);
    expect($sinMes['datos']['total'])->toBe(3)
        ->and($sinMes['entidades'])->toMatchArray(['taxon' => 'Chatobius', 'provincia' => 'Pichincha'])->not->toHaveKey('mes')
        ->and(parametrosEnlaceChat($sinMes))->not->toHaveKey('fmes');
    $mesAnio = $catalogo->responder('Busca Chatobius en Pichincha en enero de 2000');
    $febreroAnio = $catalogo->responder('¿Y en febrero?', $mesAnio['entidades']);
    expect($febreroAnio['datos']['total'])->toBe(1)
        ->and(parametrosEnlaceChat($febreroAnio))->toMatchArray(['fmes' => '2', 'ffd' => '2000-02-01', 'ffh' => '2000-02-29']);
    $sinMesAnio = $catalogo->responder('¿Y sin filtro de mes?', $febreroAnio['entidades']);
    expect($sinMesAnio['datos']['total'])->toBe(3)
        ->and(parametrosEnlaceChat($sinMesAnio))->toMatchArray(['ffd' => '2000-01-01', 'ffh' => '2000-12-31'])->not->toHaveKey('fmes');
});

test('cambiar mayo a febrero conserva el año sin ampliar intervalos diarios explícitos', function (): void {
    registrosParaContratoChat();
    $catalogo = app(ConsultaCatalogoPublico::class);
    $febrero = $catalogo->responder('¿Y en febrero?', ['taxon' => 'Chatobius', 'provincia' => 'Pichincha',
        'mes' => '5', 'desde' => '2000-05-01', 'hasta' => '2000-05-31']);
    expect($febrero['datos']['total'])->toBe(1)
        ->and(parametrosEnlaceChat($febrero))->toMatchArray(['fmes' => '2', 'ffd' => '2000-02-01', 'ffh' => '2000-02-29']);
    $anual = $catalogo->responder('Quita el filtro de mes', $febrero['entidades']);
    expect($anual['datos']['total'])->toBe(3)
        ->and(parametrosEnlaceChat($anual))->toMatchArray(['ffd' => '2000-01-01', 'ffh' => '2000-12-31'])->not->toHaveKey('fmes');
    foreach ([['2000-05-01', '2000-05-04', 1], ['2000-05-01', '2000-05-31', 2]] as [$desde, $hasta, $total]) {
        $dias = $catalogo->responder('Busca Chatobius en Pichincha desde '.$desde.' hasta '.$hasta);
        $contexto = app(ContextoChat::class);
        $contexto->guardar([], $dias);
        $conMes = $catalogo->responder('Solo los colectados en mayo', $contexto->obtener()['entities']);
        $contexto->guardar($contexto->obtener(), $conMes);
        $sinMes = $catalogo->responder('Quita el filtro de mes', $contexto->obtener()['entities']);
        expect($sinMes['datos']['total'])->toBe($total)
            ->and(parametrosEnlaceChat($sinMes))->toMatchArray(['ffd' => $desde, 'ffh' => $hasta])->not->toHaveKey('fmes');
    }
});

test('la ayuda pública prevalece sobre un código previo y reconoce CSV métricas y mapa', function (): void {
    $asistente = app(AsistentePortal::class);
    $handler = app(ConsultarChatBotHandler::class);
    $acceso = $asistente->responder('No quiero hacer un depósito. Solo quiero consultar el catálogo público, ¿necesito crear una cuenta?', $handler, contextoCatalogo: ['codigo' => 'QA3-CHAT-1']);
    expect($acceso['intent'])->toBe('portal.acceso_publico')
        ->and($acceso['texto'])->toContain('No necesitas crear una cuenta')->not->toContain('QA3-CHAT-1');
    $csv = $asistente->responder('¿Cómo descargo los resultados filtrados en CSV?', $handler, contextoCatalogo: ['taxon' => 'Chatobius', 'provincia' => 'Pichincha']);
    expect($csv['intent'])->toBe('portal.csv')
        ->and(parametrosEnlaceChat($csv))->toMatchArray(['ft' => 'Chatobius', 'fprov' => 'Pichincha']);
    expect($asistente->responder('¿Son lo mismo registros y especies?', $handler)['intent'])->toBe('portal.conteos')
        ->and($asistente->responder('¿Por qué no aparecen puntos en el mapa si hay registros con coordenadas?', $handler)['intent'])->toBe('portal.mapa_ayuda');
});

test('el conteo global del chat consulta los registros públicos sin exigir un taxón', function (): void {
    registrosParaContratoChat();
    $respuesta = app(ConsultaCatalogoPublico::class)->responder('¿Cuántos registros públicos hay en la colección?');
    expect($respuesta['intent'])->toBe('catalogo.count')
        ->and($respuesta['entidades'])->toBe([])
        ->and($respuesta['datos']['total'])->toBe(4);
});

test('una aclaración conserva la selección válida y una pregunta de acceso limpia las entidades', function (): void {
    $contexto = app(ContextoChat::class);
    $contexto->guardar([], ['fuente' => 'catalogo', 'entidades' => ['taxon' => 'Chatobius', 'elev_desde' => '1000', 'elev_hasta' => '2000']]);
    $contexto->guardar($contexto->obtener(), ['fuente' => 'aclaracion', 'intent' => 'catalogo.aclaracion']);
    expect($contexto->obtener()['entities'])->toMatchArray(['taxon' => 'Chatobius', 'elev_desde' => '1000']);
    $contexto->guardar($contexto->obtener(), ['fuente' => 'portal', 'intent' => 'portal.acceso_publico']);
    expect($contexto->obtener()['entities'])->toBe([]);
});

test('una conversación nueva exporta la selección aplicada de la página y conserva el taxón navegado entre vistas', function (): void {
    registrosParaContratoChat();
    $seleccion = ['nivel' => 'genus', 'taxon' => 'Chatobius', 'fprov' => 'Pichincha',
        'ffd' => '2000-05-01', 'ffh' => '2000-05-04', 'fed' => '1000', 'feh' => '2000'];
    $catalogo = Livewire::withQueryParams($seleccion + ['vista' => 'mapa'])->test(PortalCatalogo::class);
    $chat = Livewire::test(ChatBotWidget::class)->call('nuevaConversacion');
    expect(app(ContextoChat::class)->obtener())->toBe([])
        ->and(seleccionChatRenderizada($catalogo))->toEqual($seleccion);
    $chat->set('pregunta', '¿Cómo descargo los resultados filtrados en CSV?')->call('enviar', seleccionChatRenderizada($catalogo));
    expect(parametrosEnlaceChat($chat->get('mensajes')[1]))->toMatchArray($seleccion + ['vista' => 'registros'])
        ->not->toHaveKey('pagina');

    $catalogo->call('cambiarVista', 'registros');
    expect(seleccionChatRenderizada($catalogo))->toEqual($seleccion);
    $chat->call('sugerir', '¿Por qué no aparecen puntos en el mapa?', seleccionChatRenderizada($catalogo));
    expect(parametrosEnlaceChat($chat->get('mensajes')[3]))->toMatchArray($seleccion + ['vista' => 'mapa']);
});

test('el enlace de ayuda usa filtros aplicados y no borradores inválidos ni una consulta anterior del chat', function (): void {
    registrosParaContratoChat();
    $catalogo = Livewire::withQueryParams(['ft' => 'Chatobius', 'fprov' => 'Pichincha', 'vista' => 'registros'])->test(PortalCatalogo::class);
    $aplicada = ['ft' => 'Chatobius', 'fprov' => 'Pichincha'];
    $catalogo->set('borradorFiltros', array_replace($catalogo->get('borradorFiltros'), [
        'filtroProvincia' => 'Esmeraldas', 'filtroFechaDesde' => '2001-01-01', 'filtroFechaHasta' => '2000-01-01',
    ]));
    expect(seleccionChatRenderizada($catalogo))->toEqual($aplicada);
    $catalogo->call('aplicarBorrador')->assertHasErrors('filtroFechaHasta');
    expect(seleccionChatRenderizada($catalogo))->toEqual($aplicada);

    app(ContextoChat::class)->guardar([], ['fuente' => 'catalogo', 'entidades' => ['codigo' => 'QA3-CHAT-4']]);
    $chat = Livewire::test(ChatBotWidget::class)->set('pregunta', 'Descargar resultados CSV')->call('enviar', seleccionChatRenderizada($catalogo));
    expect(parametrosEnlaceChat($chat->get('mensajes')[1]))->toEqual($aplicada + ['vista' => 'registros']);

    $catalogo->set('borradorFiltros', array_replace($catalogo->get('borradorFiltros'), ['filtroFechaDesde' => '', 'filtroFechaHasta' => '']))
        ->call('aplicarBorrador')->assertHasNoErrors();
    $chat->call('sugerir', 'Descargar resultados CSV', seleccionChatRenderizada($catalogo));
    expect(parametrosEnlaceChat($chat->get('mensajes')[3]))->toMatchArray(['ft' => 'Chatobius', 'fprov' => 'Esmeraldas', 'vista' => 'registros'])
        ->not->toHaveKey('fc');
    $catalogo->call('limpiarFiltros');
    $chat->call('sugerir', 'Descargar resultados CSV', seleccionChatRenderizada($catalogo));
    expect(parametrosEnlaceChat($chat->get('mensajes')[5]))->toBe(['vista' => 'registros']);
});

test('la ayuda del mapa de esta selección conserva exactamente la consulta de la ficha manual', function (): void {
    $jerarquia = jerarquiaGenerosChat();
    DB::table('taxonomia.taxones')->where('id', $jerarquia['familia'])->update(['nombre_cientifico' => 'Formicidae']);
    $genero = (string) Str::uuid(); $especie = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $genero, 'padre_id' => $jerarquia['familia'], 'rango' => 'genero', 'nombre_cientifico' => 'Neoponera'],
        ['id' => $especie, 'padre_id' => $genero, 'rango' => 'especie', 'nombre_cientifico' => 'Neoponera carinulata'],
    ]);
    foreach (range(1, 13) as $i) {
        $registro = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert(['id' => $registro, 'taxon_id' => $especie,
            'codigo_catalogo' => 'QA3-FICHA-MANUAL-'.$i, 'occurrence_id' => 'QA3-FICHA-MANUAL-'.$i,
            'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $registro, 'publicado' => true]);
    }
    $seleccion = ['ft' => 'Formicidae', 'nivel' => 'species', 'taxon' => 'Neoponera carinulata'];
    $catalogo = Livewire::withQueryParams($seleccion + ['vista' => 'tarjetas', 'pagina' => 2])->test(PortalCatalogo::class)
        ->assertSet('nivel', 'species')->assertSet('taxon', 'Neoponera carinulata')
        ->assertViewHas('totalRegistrosVista', 13)->assertViewHas('paginaActual', 2)
        ->assertViewHas('especimenes', fn ($filas) => count($filas) === 6);
    $chat = Livewire::test(ChatBotWidget::class)->call('nuevaConversacion');
    expect(seleccionChatRenderizada($catalogo))->toEqual($seleccion);

    $chat->set('pregunta', '¿Cómo uso el mapa de esta selección?')->call('enviar', seleccionChatRenderizada($catalogo));
    $respuesta = $chat->get('mensajes')[1];
    expect($chat->get('mensajes'))->toHaveCount(2)
        ->and($respuesta['texto'])->toContain('La selección aplicada se conserva')
        ->not->toContain('Pulsa Aplicar filtros')
        ->and(parametrosEnlaceChat($respuesta))->toEqual($seleccion + ['vista' => 'mapa']);
});

test('las instrucciones para una consulta explícita nueva no heredan la selección aplicada ni el contexto anterior', function (): void {
    registrosParaContratoChat();
    $respuesta = app(AsistentePortal::class)->responder('¿Cómo filtro Chatobius en Esmeraldas en el mapa?', app(ConsultarChatBotHandler::class),
        contextoCatalogo: ['codigo' => 'QA3-CHAT-1', 'provincia' => 'Pichincha'],
        seleccionPortal: ['ft' => 'Formicidae', 'nivel' => 'species', 'taxon' => 'Neoponera carinulata', 'fm' => ['Fogging']]);
    expect($respuesta['intent'])->toBe('portal.filtros')
        ->and($respuesta['entidades'])->toBe(['taxon' => 'Chatobius', 'provincia' => 'Esmeraldas'])
        ->and(parametrosEnlaceChat($respuesta))->toEqual(['vista' => 'mapa', 'ft' => 'Chatobius', 'fprov' => 'Esmeraldas']);
});

test('la referencia al mapa distingue una página sin filtros de la ausencia de catálogo', function (): void {
    $asistente = app(AsistentePortal::class);
    $handler = app(ConsultarChatBotHandler::class);
    $pregunta = '¿Cómo uso el mapa de esta selección?';
    $contexto = ['taxon' => 'Chatobius', 'provincia' => 'Pichincha'];

    $sinFiltros = $asistente->responder($pregunta, $handler, contextoCatalogo: $contexto, seleccionPortal: []);
    expect(parametrosEnlaceChat($sinFiltros))->toBe(['vista' => 'mapa'])
        ->and($sinFiltros['entidades'])->toBe([]);

    $sinPagina = $asistente->responder($pregunta, $handler, contextoCatalogo: $contexto);
    expect(parametrosEnlaceChat($sinPagina))->toBe(['vista' => 'mapa', 'ft' => 'Chatobius', 'fprov' => 'Pichincha'])
        ->and($sinPagina['entidades'])->toBe($contexto);

    $sinSeleccion = $asistente->responder($pregunta, $handler);
    expect($sinSeleccion['intent'])->toBe('portal.filtros')
        ->and(parametrosEnlaceChat($sinSeleccion))->toBe(['vista' => 'mapa'])
        ->and($sinSeleccion['texto'])->toContain('Elige los criterios de tu consulta')
        ->not->toContain('La selección aplicada se conserva');
});

test('repetir la ayuda del mapa fuera del catálogo conserva la consulta pública hasta iniciar una conversación nueva', function (): void {
    registrosParaContratoChat();
    $contexto = app(ContextoChat::class);
    $seleccion = ['taxon' => 'Chatobius', 'provincia' => 'Pichincha'];
    $chat = Livewire::test(ChatBotWidget::class)->call('nuevaConversacion');
    $chat->set('pregunta', 'Busca Chatobius en Pichincha')->call('enviar');
    expect($contexto->obtener()['entities'])->toBe($seleccion);

    $pregunta = '¿Cómo uso el mapa de esta selección?';
    $chat->set('pregunta', $pregunta)->call('enviar');
    $primeraAyuda = $chat->get('mensajes')[3];
    expect(parametrosEnlaceChat($primeraAyuda))->toBe(['vista' => 'mapa', 'ft' => 'Chatobius', 'fprov' => 'Pichincha'])
        ->and($contexto->obtener()['entities'])->toBe($seleccion)
        ->and($primeraAyuda['texto'])->toContain('La selección aplicada se conserva')
        ->not->toContain('Pulsa Aplicar filtros');

    $chat->set('pregunta', $pregunta)->call('enviar');
    expect($chat->get('mensajes')[5]['opciones'][0]['url'])->toBe($primeraAyuda['opciones'][0]['url'])
        ->and($contexto->obtener()['entities'])->toBe($seleccion);

    $chat->call('nuevaConversacion');
    expect($contexto->obtener())->toBe([])
        ->and($chat->get('mensajes'))->toBe([]);
});

test('los enlaces de selección admiten solo parámetros públicos y conservan los filtros que el chat no interpreta', function (): void {
    $entrada = ['fp' => ['Alcohol'], 'fm' => ['Trampa de caída'], 'fb' => ['Bosque'], 'fg' => ['Quito', 'Yasuní'],
        'fco' => 'Colectora', 'fh' => 'Hojarasca', 'fsti' => 'Holotype', 'fd' => 'En préstamo', 'fca' => 'Worker', 'fes' => 'Adult',
        'flat' => '-1', 'flax' => '0', 'flon' => '-79', 'flox' => '-78', 'fgeo' => '1', 'fap' => '1'];
    $seleccion = array_replace($entrada, ['fm' => ['trampa de caída'], 'fd' => 'on_loan']);
    $catalogo = Livewire::withQueryParams($entrada + ['vista' => 'registros'])->test(PortalCatalogo::class);
    expect(seleccionChatRenderizada($catalogo))->toEqual($seleccion);
    $respuesta = app(AsistentePortal::class)->responder('¿Cómo descargo CSV?', app(ConsultarChatBotHandler::class),
        seleccionPortal: $seleccion + ['vista' => 'mapa', 'pagina' => 9, 'especimenes' => [['id' => 'privado']], 'borradorFiltros' => ['fprov' => 'Napo']]);
    expect(parametrosEnlaceChat($respuesta))->toEqual($seleccion + ['vista' => 'registros'])
        ->and(EnlaceSeleccionCatalogo::limpiar(['ft' => ['id' => 'privado'], 'fg' => ['Quito', ['id' => 'privado']]]))->toBe(['fg' => ['Quito']]);
});

test('las variantes de fechas naturales conservan ambos días y el operador antes del año', function (): void {
    registrosParaContratoChat();
    $catalogo = app(ConsultaCatalogoPublico::class);
    $respuesta = $catalogo->responder('Busca Chatobius en Pichincha desde el 1 de mayo de 2000 hasta el 4 de mayo de 2000');
    expect($respuesta['datos']['total'])->toBe(1)
        ->and(parametrosEnlaceChat($respuesta))->toMatchArray(['ffd' => '2000-05-01', 'ffh' => '2000-05-04'])
        ->not->toHaveKey('fmes');
    foreach (['desde el 1 de mayo de 2000 hasta el próximo martes', 'desde el 1 de mayo de 2000 y el 4 de mayo de 2000'] as $intervalo) {
        $incompleta = $catalogo->responder('Busca Chatobius '.$intervalo);
        expect($incompleta['intent'])->toBe('catalogo.aclaracion')->and($incompleta['datos']['total'])->toBeNull();
    }
    DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->update(['fecha_colecta' => '1949-12-31']);
    DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-2')->update(['fecha_colecta' => '1950-01-01']);
    $antes = $catalogo->responder('Busca Chatobius colectados antes del 1950');
    expect($antes['datos']['total'])->toBe(1)
        ->and(parametrosEnlaceChat($antes))->toMatchArray(['ffh' => '1949-12-31'])->not->toHaveKey('ffd');
});

test('los géneros de todos los niveles superiores usan la provincia fechas y elevación del enlace', function (): void {
    jerarquiaGenerosChat();
    $catalogo = app(ConsultaCatalogoPublico::class);
    foreach (['Chatanimalia', 'Chatarthropoda', 'Chatinsecta', 'Chathymenoptera', 'Chatformicidae'] as $raiz) {
        $respuesta = $catalogo->responder('¿Qué géneros hay dentro de '.$raiz.' en Pichincha desde 2000-05-01 hasta 2000-05-04 entre 1000 y 2000 metros de altitud?');
        expect($respuesta['intent'])->toBe('catalogo.genera')->and($respuesta['datos'])->toBe(['total' => 1, 'filas' => ['Chatobius']])
            ->and($respuesta['entidades'])->toMatchArray(['taxon' => $raiz, 'provincia' => 'Pichincha', 'desde' => '2000-05-01', 'hasta' => '2000-05-04', 'elev_desde' => '1000', 'elev_hasta' => '2000'])
            ->and(parametrosEnlaceChat($respuesta))->toMatchArray(['ft' => $raiz, 'fprov' => 'Pichincha', 'ffd' => '2000-05-01', 'ffh' => '2000-05-04', 'fed' => '1000', 'feh' => '2000']);
    }
    expect($catalogo->responder('¿Qué géneros hay dentro de Chatarthropoda en Esmeraldas?')['datos'])->toBe(['total' => 1, 'filas' => ['Chatoterus']])
        ->and($catalogo->responder('¿Qué géneros hay dentro de Chatarthropoda en Ecuador?')['datos'])->toBe(['total' => 1, 'filas' => ['Chatoterus']]);
    $registro = DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->value('id');
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $registro)->update(['occurrence_id_visible' => false]);
    $pregunta = '¿Qué géneros hay dentro de Chatformicidae desde 2000-05-01 hasta 2000-05-04?';
    expect($catalogo->responder($pregunta)['datos'])->toBe(['total' => 1, 'filas' => ['Chatobius']]);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $registro)->update(['genus_visible' => false]);
    expect($catalogo->responder($pregunta)['datos'])->toBe(['total' => 0, 'filas' => []]);
});

test('el total de géneros incluye identificaciones al género y no se limita a los diez nombres mostrados', function (): void {
    $jerarquia = jerarquiaGenerosChat();
    foreach (range('a', 'j') as $sufijo) {
        $genero = (string) Str::uuid(); $registro = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $genero, 'padre_id' => $jerarquia['familia'], 'rango' => 'genero', 'nombre_cientifico' => 'Chatogenus'.$sufijo]);
        DB::table('taxonomia.especimenes')->insert(['id' => $registro, 'taxon_id' => $genero, 'codigo_catalogo' => 'QA-GENERO-'.$sufijo, 'occurrence_id' => 'QA-GENERO-'.$sufijo,
            'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $registro]);
    }
    $respuesta = app(ConsultaCatalogoPublico::class)->responder('¿Cuántos géneros hay dentro de Chatarthropoda?');
    expect($respuesta['datos']['total'])->toBe(12)->and($respuesta['datos']['filas'])->toHaveCount(10)
        ->and($respuesta['texto'])->toContain('se muestran los primeros 10');
});

test('una consulta global explícita deja el código o taxón anterior y conserva el historial de mensajes', function (): void {
    registrosParaContratoChat();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    foreach ([['codigo' => 'QA3-CHAT-1'], ['taxon' => 'Chatobius alpha', 'provincia' => 'Pichincha', 'mes' => '5']] as $contexto) {
        foreach (['¿Cuántos registros públicos hay en la colección?' => 4, '¿Cuántas especies distintas hay en toda la colección?' => 2] as $pregunta => $total) {
            $respuesta = $asistente->responder($pregunta, $handler, contextoCatalogo: $contexto);
            expect($respuesta['datos']['total'])->toBe($total)->and($respuesta['entidades'])->toBe([])
                ->and(parametrosEnlaceChat($respuesta))->toBe(['vista' => 'registros']);
        }
    }
    $chat = Livewire::test(ChatBotWidget::class)->set('pregunta', 'Busca QA3-CHAT-1')->call('enviar');
    expect(app(ContextoChat::class)->obtener()['entities']['codigo'])->toBe('QA3-CHAT-1');
    $chat->set('pregunta', '¿Cuántos registros públicos hay en la colección?')->call('enviar');
    expect($chat->get('mensajes'))->toHaveCount(4)->and($chat->get('mensajes')[1]['texto'])->toContain('QA3-CHAT-1')
        ->and($chat->get('mensajes')[3]['texto'])->toContain('4 registros publicados')
        ->and(app(ContextoChat::class)->obtener()['entities'])->toBe([]);
    $referida = $asistente->responder('¿Cuántas especies de ese taxón hay en la colección?', $handler, contextoCatalogo: ['taxon' => 'Chatobius alpha']);
    expect($referida['datos']['total'])->toBe(1)->and($referida['entidades'])->toBe(['taxon' => 'Chatobius alpha']);
});

test('retirar un filtro reconocido conserva los demás predicados del contexto y del enlace', function (string $pregunta, array $contexto, array $esperadas, int $total): void {
    registrosParaContratoChat();
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class), contextoCatalogo: $contexto);
    expect($respuesta['datos']['total'])->toBe($total)->and($respuesta['entidades'])->toEqual($esperadas)
        ->and(parametrosEnlaceChat($respuesta))->toEqual(app(ConsultaCatalogoPublico::class)->parametros($esperadas));
    app(ContextoChat::class)->guardar(['entities' => $contexto], $respuesta);
    expect(app(ContextoChat::class)->obtener()['entities'])->toEqual($esperadas);
})->with([
    ['Quita el filtro de provincia', ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'mes' => '5'], ['taxon' => 'Chatobius', 'mes' => '5'], 3],
    ['Elimina el filtro de país', ['taxon' => 'Chatobius', 'pais' => 'Ecuador', 'mes' => '5'], ['taxon' => 'Chatobius', 'mes' => '5'], 3],
    ['Quita el filtro de localidad', ['taxon' => 'Chatobius', 'localidad' => 'Quito'], ['taxon' => 'Chatobius'], 4],
    ['Quita el filtro de código', ['codigo' => 'QA3-CHAT-1', 'provincia' => 'Pichincha', 'mes' => '5'], ['provincia' => 'Pichincha', 'mes' => '5'], 2],
    ['Quita el filtro de taxón', ['taxon' => 'Chatobius alpha'], [], 4],
    ['Sin filtro de mes', ['taxon' => 'Chatobius', 'mes' => '5', 'desde' => '2000-05-01', 'hasta' => '2000-05-31', 'fecha_precision' => 'mes'], ['taxon' => 'Chatobius', 'desde' => '2000-01-01', 'hasta' => '2000-12-31', 'fecha_precision' => 'explicita'], 4],
    ['Quita el filtro de fechas', ['taxon' => 'Chatobius', 'desde' => '2000-05-01', 'hasta' => '2000-05-04', 'fecha_precision' => 'explicita'], ['taxon' => 'Chatobius'], 4],
    ['Quita el filtro de fecha inicial', ['taxon' => 'Chatobius', 'desde' => '2000-05-04', 'hasta' => '2000-05-05', 'fecha_precision' => 'explicita'], ['taxon' => 'Chatobius', 'hasta' => '2000-05-05'], 4],
    ['Quita el filtro de fecha final', ['taxon' => 'Chatobius', 'desde' => '2000-05-04', 'hasta' => '2000-05-05', 'fecha_precision' => 'explicita'], ['taxon' => 'Chatobius', 'desde' => '2000-05-04'], 2],
    ['Quita el filtro de elevación', ['taxon' => 'Chatobius', 'elev_desde' => '1000', 'elev_hasta' => '2000'], ['taxon' => 'Chatobius'], 4],
    ['Quita el filtro de coordenadas', ['taxon' => 'Chatobius', 'ubicacion' => '1'], ['taxon' => 'Chatobius'], 4],
    ['Quita el filtro de identificación', ['taxon' => 'Chatobius', 'identificacion' => 'superior'], ['taxon' => 'Chatobius'], 4],
]);

test('una retirada desconocida ambigua o sin filtro previo pide aclaración sin cambiar la selección', function (): void {
    registrosParaContratoChat();
    $contexto = ['taxon' => 'Chatobius', 'provincia' => 'Pichincha'];
    foreach ([['Quita el filtro de método de recolección', $contexto], ['Quita el filtro de provincia Pichincha', $contexto],
        ['Quita el filtro de provincia', ['taxon' => 'Chatobius']], ['Quita el filtro de provincia', []]] as [$pregunta, $anterior]) {
        $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class), contextoCatalogo: $anterior);
        expect($respuesta['intent'])->toBe('catalogo.aclaracion')->and($respuesta['datos']['total'])->toBeNull()
            ->and($respuesta['entidades'])->toBe($anterior);
    }
});

test('añadir o reemplazar una dimensión geográfica conserva las otras restricciones explícitas', function (string $pregunta, array $contexto, array $esperadas, int $total): void {
    registrosParaContratoChat();
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class), contextoCatalogo: $contexto);
    expect($respuesta['datos']['total'])->toBe($total)->and($respuesta['entidades'])->toEqual($esperadas)
        ->and(parametrosEnlaceChat($respuesta))->toEqual(app(ConsultaCatalogoPublico::class)->parametros($esperadas));
})->with([
    ['Solo los de Ecuador', ['taxon' => 'Chatobius', 'provincia' => 'Pichincha'], ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'pais' => 'Ecuador'], 0],
    ['Y en Pichincha', ['taxon' => 'Chatobius', 'localidad' => 'Quito'], ['taxon' => 'Chatobius', 'localidad' => 'Quito', 'provincia' => 'Pichincha'], 1],
    ['Solo los de la localidad de Quito', ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'pais' => 'Peru'], ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'pais' => 'Peru', 'localidad' => 'Quito'], 1],
    ['Y en Pichincha', ['taxon' => 'Chatobius', 'provincia' => 'Esmeraldas', 'pais' => 'Ecuador'], ['taxon' => 'Chatobius', 'pais' => 'Ecuador', 'provincia' => 'Pichincha'], 0],
]);

test('una corrección geográfica retira solo el valor previo negado y conserva el resto de la selección', function (string $pregunta, array $contexto, array $esperadas, int $total): void {
    registrosParaContratoChat();
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class), contextoCatalogo: $contexto);
    expect($respuesta['datos']['total'])->toBe($total)
        ->and($respuesta['entidades'])->toEqual($esperadas)
        ->and(parametrosEnlaceChat($respuesta))->toEqual(app(ConsultaCatalogoPublico::class)->parametros($esperadas));
    app(ContextoChat::class)->guardar(['entities' => $contexto], $respuesta);
    expect(app(ContextoChat::class)->obtener()['entities'])->toEqual($esperadas);
})->with([
    ['No Pichincha, Quito',
        ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'pais' => 'Perú', 'mes' => '2', 'desde' => '2000-02-01', 'hasta' => '2000-02-29', 'fecha_precision' => 'explicita'],
        ['taxon' => 'Chatobius', 'pais' => 'Perú', 'mes' => '2', 'desde' => '2000-02-01', 'hasta' => '2000-02-29', 'fecha_precision' => 'explicita', 'localidad' => 'Quito'], 1],
    ['No la provincia de Pichincha, Perú',
        ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'localidad' => 'Pichincha', 'pais' => 'Ecuador'],
        ['taxon' => 'Chatobius', 'localidad' => 'Pichincha', 'pais' => 'Peru'], 1],
    ['No la localidad de Pichincha, Perú',
        ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'localidad' => 'Pichincha', 'pais' => 'Ecuador'],
        ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'pais' => 'Peru'], 3],
    ['No Perú, Quito',
        ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'pais' => 'Perú', 'mes' => '2'],
        ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'mes' => '2', 'localidad' => 'Quito'], 1],
    ['No Esmeraldas, Quito',
        ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'pais' => 'Peru'],
        ['taxon' => 'Chatobius', 'provincia' => 'Pichincha', 'pais' => 'Peru', 'localidad' => 'Quito'], 1],
]);
