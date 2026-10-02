<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\DetectorEntidadesChat;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

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
            'elevation_min_m' => $elevacion, 'elevation_max_m' => $elevacion, 'colector' => 'Prueba']);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id]);
    }
}

function parametrosEnlaceChat(array $respuesta): array
{
    parse_str((string) parse_url($respuesta['opciones'][0]['url'], PHP_URL_QUERY), $parametros);
    return $parametros;
}

test('consultar una provincia no añade una localidad homónima ni una nota con provincia de', function (): void {
    registrosParaContratoChat();
    foreach (['¿Cuántas especies distintas hay en Pichincha?', '¿Cuántas especies distintas hay en la provincia de Pichincha?'] as $pregunta) {
        expect(app(DetectorEntidadesChat::class)->extraer($pregunta))->toBe(['provincia' => 'Pichincha']);
    }
    expect(app(DetectorEntidadesChat::class)->extraer('Busca Chatobius en la localidad de Pichincha'))
        ->toMatchArray(['taxon' => 'Chatobius', 'localidad' => 'Pichincha'])->not->toHaveKey('provincia');
    foreach (['¿Cuántas especies distintas de Chatobius hay en Pichincha?', '¿Cuántas especies distintas de Chatobius hay en la provincia de Pichincha?'] as $pregunta) {
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
            ->and($respuesta['entidades']['taxon'])->toBe('Chatobius');
    }
    expect(DB::table('taxonomia.especimenes')->where('occurrence_id', 'QA3-CHAT-2')->value('country'))->toBe('Perú');
    foreach (['Yasuní', 'Yasuni'] as $indice => $localidad) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert(['id' => $id, 'codigo_catalogo' => 'QA3-CHAT-YASUN-'.($indice + 1),
            'occurrence_id' => 'QA3-CHAT-YASUN-'.($indice + 1), 'taxon_id' => DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Chatobius alpha')->value('id'),
            'localidad' => $localidad, 'locality_name' => $localidad, 'fecha_colecta' => '2000-05-05', 'colector' => 'Prueba']);
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
