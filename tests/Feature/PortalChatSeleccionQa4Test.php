<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

/** Reutiliza las identificaciones y los cuatro registros públicos del contrato QA3. */
function seleccionCompletaChatQa4(): array
{
    jerarquiaGenerosChat();
    // Los cuatro registros siguen siendo geográficamente públicos. Sólo el primero
    // cae en los límites QA4; los demás no dependen de coordenadas ausentes para salir.
    DB::table('taxonomia.especimenes')->whereIn('codigo_catalogo', ['QA3-CHAT-2', 'QA3-CHAT-3', 'QA3-CHAT-4'])
        ->update(['decimal_latitude' => -2, 'decimal_longitude' => -77]);
    DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-1')->update([
        'preparations' => 'Alcohol QA4', 'colector' => 'Colectora QA4', 'sampling_protocol' => 'Red QA4',
        'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5, 'biome' => 'Bosque QA4',
        'habitat' => 'Hojarasca QA4', 'type_status' => 'Holotype', 'caste' => 'Worker', 'life_stage' => 'Adult',
    ]);

    return ['nivel' => 'species', 'taxon' => 'Chatobius alpha', 'fc' => 'QA3-CHAT-1', 'ft' => 'Chatarthropoda',
        'fp' => ['Alcohol QA4'], 'fg' => ['Pichincha'], 'fco' => 'Colectora QA4',
        'ffd' => '2000-05-01', 'ffh' => '2000-05-04', 'fm' => ['Red QA4'],
        'flat' => '-1', 'flax' => '0', 'flon' => '-79', 'flox' => '-78', 'fed' => '1000', 'feh' => '2000',
        'fb' => ['Bosque QA4'], 'fh' => 'Hojarasca QA4', 'fsti' => 'Holotype', 'fca' => 'Worker', 'fes' => 'Adult',
        'fpais' => 'Peru', 'fprov' => 'Pichincha',
        'fph' => DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Chatarthropoda')->value('id'),
        'fmes' => '5', 'fid' => 'especie', 'fgeo' => '1', 'fap' => '1'];
}

test('el conteo contextual usa la página aplicada completa aunque el chat y el borrador tengan otras consultas', function (): void {
    $seleccion = seleccionCompletaChatQa4();
    $catalogo = Livewire::withQueryParams($seleccion + ['vista' => 'mapa', 'pagina' => 2])->test(PortalCatalogo::class);
    $catalogo->set('borradorFiltros.filtroMetodos', ['Otra técnica pendiente']);
    $chat = Livewire::test(ChatBotWidget::class)->call('nuevaConversacion');
    $chat->set('pregunta', 'Busca Chatoterus')->call('enviar');
    expect(app(ContextoChat::class)->obtener()['entities']['taxon'])->toBe('Chatoterus')
        ->and(seleccionChatRenderizada($catalogo))->toEqual($seleccion);

    $chat->set('pregunta', '¿Cuántos registros hay en esta selección?')->call('enviar', seleccionChatRenderizada($catalogo));
    $respuesta = $chat->get('mensajes')[3];
    expect($respuesta['texto'])->toContain('Hay 1 registro publicado en la selección aplicada de la página')
        ->and(parametrosEnlaceChat($respuesta))->toEqual($seleccion + ['vista' => 'registros'])
        ->and(app(ContextoChat::class)->obtener()['entities'])->toBe([]);

    $catalogo->call('cambiarVista', 'tarjetas');
    $chat->set('pregunta', '¿Cuántos ejemplares hay en la selección actual?')->call('enviar', seleccionChatRenderizada($catalogo));
    expect($chat->get('mensajes')[5]['texto'])->toContain('Hay 1 registro publicado en la selección aplicada de la página')
        ->and(parametrosEnlaceChat($chat->get('mensajes')[5]))->toEqual($seleccion + ['vista' => 'registros']);

    $chat->set('pregunta', '¿Cómo uso el mapa de esta selección?')->call('enviar', seleccionChatRenderizada($catalogo));
    expect(parametrosEnlaceChat($chat->get('mensajes')[7]))->toEqual($seleccion + ['vista' => 'mapa'])
        ->and($chat->get('mensajes')[7]['texto'])->not->toContain('Pulsa Aplicar filtros');
});

test('cada dimensión pública aplicada participa en el conteo y en su enlace aunque no exista en el parser', function (array $filtros, int $total, array $datosNoAptos = []): void {
    seleccionCompletaChatQa4();
    // Aptitud debe distinguir una fecha pública disponible de tres fechas ausentes;
    // este estado pertenece sólo a ese dataset y no modifica los casos de mes/fecha.
    if ($datosNoAptos !== []) {
        DB::table('taxonomia.especimenes')->whereIn('codigo_catalogo', ['QA3-CHAT-2', 'QA3-CHAT-3', 'QA3-CHAT-4'])
            ->update($datosNoAptos);
    }
    $respuesta = app(AsistentePortal::class)->responder('¿Cuántos registros hay en esta selección?', app(ConsultarChatBotHandler::class),
        contextoCatalogo: ['codigo' => 'QA3-CHAT-4'], seleccionPortal: $filtros);
    expect($respuesta['intent'])->toBe('catalogo.count')
        ->and($respuesta['datos']['total'])->toBe($total)
        ->and(parametrosEnlaceChat($respuesta))->toEqual($filtros + ['vista' => 'registros']);
})->with([
    'preparación' => [['fp' => ['Alcohol QA4']], 1],
    'colectora' => [['fco' => 'Colectora QA4'], 1],
    'técnica de colecta' => [['fm' => ['Red QA4']], 1],
    'bioma' => [['fb' => ['Bosque QA4']], 1],
    'hábitat' => [['fh' => 'Hojarasca QA4'], 1],
    'tipo' => [['fsti' => 'Holotype'], 1],
    'casta' => [['fca' => 'Worker'], 1],
    'estadio' => [['fes' => 'Adult'], 1],
    'latitud' => [['flat' => '-1', 'flax' => '0'], 1],
    'longitud' => [['flon' => '-79', 'flox' => '-78'], 1],
    'aptitud' => [['fap' => '1'], 1, ['fecha_colecta' => null]],
    // El par público válido ya es una condición global del portal: conserva los cuatro.
    'coordenadas públicas' => [['fgeo' => '1'], 4],
    'código' => [['fc' => 'QA3-CHAT-1'], 1],
    'nombre científico' => [['ft' => 'Chatoterus'], 1],
    'localidad' => [['fg' => ['Quito']], 1],
    'país' => [['fpais' => 'Ecuador'], 1],
    'provincia' => [['fprov' => 'Esmeraldas'], 1],
    'mes' => [['fmes' => '2'], 1],
    'identificación superior' => [['fid' => 'superior'], 0],
    'filo por UUID' => [['fph' => '11111111-1111-4111-8111-111111111111'], 0],
    'fechas' => [['ffd' => '2000-05-01', 'ffh' => '2000-05-04'], 1],
    'elevación' => [['fed' => '1600', 'feh' => '2000'], 1],
    'jerarquía exacta' => [['nivel' => 'species', 'taxon' => 'Chatobius alpha'], 1],
    'cero confirmado' => [['fm' => ['Técnica inexistente']], 0],
]);

test('la selección vacía es autoritativa y una consulta global o explícita nueva no hereda la página', function (): void {
    $seleccion = seleccionCompletaChatQa4();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $vacia = $asistente->responder('¿Cuántos registros hay en esta selección?', $handler,
        contextoCatalogo: ['codigo' => 'QA3-CHAT-1'], seleccionPortal: []);
    expect($vacia['datos']['total'])->toBe(4)->and($vacia['entidades'])->toBe([])
        ->and(parametrosEnlaceChat($vacia))->toBe(['vista' => 'registros']);
    foreach (['¿Cuántos registros hay en toda la colección?' => 4, '¿Cuántos géneros distintos hay en la colección?' => 2, '¿Cuántas familias hay en toda la colección?' => 1] as $pregunta => $total) {
        $global = $asistente->responder($pregunta, $handler, contextoCatalogo: ['codigo' => 'QA3-CHAT-1'], seleccionPortal: $seleccion);
        expect($global['datos']['total'])->toBe($total)->and($global['entidades'])->toBe([])
            ->and(parametrosEnlaceChat($global))->toBe(['vista' => 'registros']);
    }
    $nueva = $asistente->responder('¿Cuántos registros de Chatoterus hay en Ecuador?', $handler,
        contextoCatalogo: ['codigo' => 'QA3-CHAT-1'], seleccionPortal: $seleccion);
    expect($nueva['datos']['total'])->toBe(1)
        ->and(parametrosEnlaceChat($nueva))->toBe(['vista' => 'registros', 'fpais' => 'Ecuador', 'ft' => 'Chatoterus']);
});

test('las unidades de la selección cuentan diversidad científica y no sustituyen familias o géneros por registros', function (): void {
    jerarquiaGenerosChat();
    $consulta = app(ConsultaCatalogoPublico::class);
    foreach (['especies' => ['catalogo.species', 2], 'géneros' => ['catalogo.genera', 1], 'familias' => ['catalogo.families', 1]] as $unidad => [$intent, $total]) {
        $respuesta = $consulta->responder('¿Cuántas '.$unidad.' hay en esta selección?', seleccionPortal: ['fprov' => 'Pichincha']);
        expect($respuesta['intent'])->toBe($intent)->and($respuesta['datos']['total'])->toBe($total)
            ->and($respuesta['texto'])->toContain('cada nombre científico válido')->toContain('selección aplicada de la página')
            ->not->toContain('3 registros publicados')
            ->and(parametrosEnlaceChat($respuesta))->toBe(['fprov' => 'Pichincha', 'vista' => 'registros']);
    }
});

test('la referencia sin selección pide aclaración y los parámetros inválidos no se convierten en un conteo global', function (array $seleccion): void {
    registrosParaContratoChat();
    $consulta = app(ConsultaCatalogoPublico::class);
    $respuesta = $consulta->responder('¿Cuántos registros hay en esta selección?', seleccionPortal: $seleccion);
    expect($respuesta['intent'])->toBe('catalogo.aclaracion')->and($respuesta['datos']['total'])->toBeNull()
        ->and($respuesta['texto'])->toContain('criterios inválidos')->toContain('no he calculado un conteo parcial');
    $ausente = $consulta->responder('¿Cuántos registros hay en esta selección?');
    expect($ausente['intent'])->toBe('catalogo.aclaracion')->and($ausente['datos']['total'])->toBeNull();
})->with([
    'fecha imposible' => [['ffd' => '2000-02-30']],
    'intervalo invertido' => [['ffd' => '2000-05-05', 'ffh' => '2000-05-01']],
    'límite incompleto' => [['flat' => '-1']],
    'elevación invertida desde cero' => [['fed' => '0', 'feh' => '-1']],
    'mes inválido' => [['fmes' => '13']],
    'rango sin nombre' => [['nivel' => 'species']],
    'taxón no escalar' => [['ft' => ['id' => 'privado']]],
    'lista con objeto' => [['fm' => ['Red QA4', ['id' => 'privado']]]],
]);

test('el conteo ignora datos privados borradores y paginado de la carga del cliente', function (): void {
    $seleccion = seleccionCompletaChatQa4();
    $respuesta = app(ConsultaCatalogoPublico::class)->responder('¿Cuántos registros hay en esta selección?', seleccionPortal: $seleccion + [
        'pagina' => 9, 'vista' => 'mapa', 'borradorFiltros' => ['filtroTaxon' => 'Reservado'],
        'especimenes' => [['id' => 'UUID-PRIVADO']], 'error_consulta' => 'INFORMACIÓN PRIVADA',
    ]);
    expect($respuesta['datos']['total'])->toBe(1)->and($respuesta['entidades'])->toBe([])
        ->and(parametrosEnlaceChat($respuesta))->toEqual($seleccion + ['vista' => 'registros'])
        ->and(json_encode($respuesta, JSON_THROW_ON_ERROR))->not->toContain('UUID-PRIVADO')->not->toContain('INFORMACIÓN PRIVADA')->not->toContain('Reservado');
});

test('los géneros y familias globales cuentan todas las identificaciones válidas aunque solo presenten diez nombres', function (): void {
    $jerarquia = jerarquiaGenerosChat();
    $orden = DB::table('taxonomia.taxones')->where('id', $jerarquia['familia'])->value('padre_id');
    foreach (range('a', 'k') as $sufijo) {
        $familia = (string) Str::uuid(); $genero = (string) Str::uuid(); $registro = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert([
            ['id' => $familia, 'padre_id' => $orden, 'rango' => 'familia', 'nombre_cientifico' => 'Chatfamilia'.$sufijo],
            ['id' => $genero, 'padre_id' => $familia, 'rango' => 'genero', 'nombre_cientifico' => 'Chatgenero'.$sufijo],
        ]);
        DB::table('taxonomia.especimenes')->insert(['id' => $registro, 'taxon_id' => $genero, 'codigo_catalogo' => 'QA4-RANGO-'.$sufijo, 'occurrence_id' => 'QA4-RANGO-'.$sufijo,
            'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $registro]);
    }
    foreach (['géneros' => ['catalogo.genera', 13], 'familias' => ['catalogo.families', 12]] as $unidad => [$intent, $total]) {
        $respuesta = app(AsistentePortal::class)->responder('¿Cuántos '.$unidad.' distintos hay en toda la colección?', app(ConsultarChatBotHandler::class), contextoCatalogo: ['codigo' => 'QA3-CHAT-1']);
        expect($respuesta['intent'])->toBe($intent)->and($respuesta['datos']['total'])->toBe($total)
            ->and($respuesta['datos']['filas'])->toHaveCount(10)
            ->and($respuesta['texto'])->toContain('cada nombre científico válido')->toContain('se muestran '.($unidad === 'géneros' ? 'los primeros 10' : 'las primeras 10'))
            ->and(parametrosEnlaceChat($respuesta))->toBe(['vista' => 'registros']);
    }
    $familias = app(ConsultaCatalogoPublico::class)->responder('¿Cuántas familias de Chatarthropoda hay?');
    expect($familias['datos']['total'])->toBe(12)->and($familias['texto'])->toContain('12 familias publicadas')->not->toContain('15 registros publicados');
});

test('las formulaciones exactas QA4 de géneros globales y familias por filo resuelven su propia unidad', function (): void {
    jerarquiaGenerosChat();
    DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Chatarthropoda')->update(['nombre_cientifico' => 'Arthropoda']);
    foreach (['Mollusca' => ['Chatmollidae', 'Chatmollus'], 'Annelida' => ['Chatannelidae', 'Chatannelus']] as $filo => [$nombreFamilia, $nombreGenero]) {
        $phylum = (string) Str::uuid(); $familia = (string) Str::uuid(); $genero = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert([
            ['id' => $phylum, 'padre_id' => null, 'rango' => 'phylum', 'nombre_cientifico' => $filo],
            ['id' => $familia, 'padre_id' => $phylum, 'rango' => 'familia', 'nombre_cientifico' => $nombreFamilia],
            ['id' => $genero, 'padre_id' => $familia, 'rango' => 'genero', 'nombre_cientifico' => $nombreGenero],
        ]);
        foreach ([1, 2] as $i) {
            $registro = (string) Str::uuid();
            DB::table('taxonomia.especimenes')->insert(['id' => $registro, 'taxon_id' => $genero,
                'codigo_catalogo' => 'QA4-'.$filo.'-'.$i, 'occurrence_id' => 'QA4-'.$filo.'-'.$i,
                'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5]);
            DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $registro]);
        }
    }
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    foreach ([
        '¿Cuántos géneros hay en toda la colección?' => ['catalogo.genera', 4],
        '¿Cuántos géneros distintos hay en la colección?' => ['catalogo.genera', 4],
        '¿Cuántas familias de Arthropoda hay?' => ['catalogo.families', 1],
        '¿Cuántas familias de Mollusca hay?' => ['catalogo.families', 1],
        '¿Cuántos géneros de Annelida hay?' => ['catalogo.genera', 1],
        '¿Cuántos géneros de Mollusca hay?' => ['catalogo.genera', 1],
    ] as $pregunta => [$intent, $total]) {
        $respuesta = $asistente->responder($pregunta, $handler);
        expect($respuesta['intent'])->toBe($intent)->and($respuesta['datos']['total'])->toBe($total)
            ->and($respuesta['texto'])->toContain('cada nombre científico válido')->not->toContain('registros publicados');
    }
});

test('los agregados taxonómicos respetan permisos por registro y no confirman hijos de una nota curatorial', function (): void {
    $jerarquia = jerarquiaGenerosChat();
    $phylum = DB::table('taxonomia.taxones')->where('nombre_cientifico', 'Chatarthropoda')->value('id');
    $nota = (string) Str::uuid(); $familia = (string) Str::uuid(); $genero = (string) Str::uuid(); $especie = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $nota, 'padre_id' => $phylum, 'rango' => 'clase', 'nombre_cientifico' => 'MUESTRAS REUBICADAS EN COLECCIÓN'],
        ['id' => $familia, 'padre_id' => $nota, 'rango' => 'familia', 'nombre_cientifico' => 'Chatnotidae'],
        ['id' => $genero, 'padre_id' => $familia, 'rango' => 'genero', 'nombre_cientifico' => 'Chatnota'],
        ['id' => $especie, 'padre_id' => $genero, 'rango' => 'especie', 'nombre_cientifico' => 'Chatnota alpha'],
    ]);
    $registro = (string) Str::uuid();
    DB::table('taxonomia.especimenes')->insert(['id' => $registro, 'taxon_id' => $especie, 'codigo_catalogo' => 'QA4-CURATORIAL', 'occurrence_id' => 'QA4-CURATORIAL',
        'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5]);
    DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $registro]);
    $consulta = app(ConsultaCatalogoPublico::class);
    expect($consulta->responder('¿Cuántos géneros hay en toda la colección?')['datos']['total'])->toBe(2)
        ->and($consulta->responder('¿Cuántas familias de Chatarthropoda hay?')['datos']['total'])->toBe(1)
        ->and($consulta->responder('¿Cuántos registros hay en esta selección?', seleccionPortal: [])['datos']['total'])->toBe(5)
        ->and($consulta->responder('¿Cuántas especies hay en esta selección?', seleccionPortal: [])['datos']['total'])->toBe(3)
        ->and($consulta->responder('¿Cuántas especies hay en toda la colección?')['datos']['total'])->toBe(3);

    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-4')->select('id'))
        ->update(['scientific_name_visible' => false]);
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', DB::table('taxonomia.especimenes')->whereIn('codigo_catalogo', ['QA3-CHAT-1', 'QA3-CHAT-2', 'QA3-CHAT-3'])->select('id'))
        ->update(['genus_visible' => false, 'family_visible' => false, 'occurrence_id_visible' => false]);
    $generos = $consulta->responder('¿Cuántos géneros hay en toda la colección?');
    $familias = $consulta->responder('¿Cuántas familias hay en toda la colección?');
    expect($generos['datos'])->toBe(['total' => 0, 'filas' => []])
        ->and($familias['datos'])->toBe(['total' => 0, 'filas' => []])
        ->and(json_encode([$generos, $familias], JSON_THROW_ON_ERROR))->not->toContain('Chatobius')->not->toContain('Chatoterus')->not->toContain('Chatnota')->not->toContain($genero)->not->toContain($especie);
});

test('una nota solo en la hoja conserva sus padres confirmados en diversidad y ranking sin convertirla en especie', function (): void {
    $jerarquia = jerarquiaGenerosChat();
    $orden = DB::table('taxonomia.taxones')->where('id', $jerarquia['familia'])->value('padre_id');
    $familia = (string) Str::uuid(); $genero = (string) Str::uuid(); $hoja = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $familia, 'padre_id' => $orden, 'rango' => 'familia', 'nombre_cientifico' => 'Chathojidae'],
        ['id' => $genero, 'padre_id' => $familia, 'rango' => 'genero', 'nombre_cientifico' => 'Chathojus'],
        ['id' => $hoja, 'padre_id' => $genero, 'rango' => 'especie', 'nombre_cientifico' => 'IDENTIFICACIÓN DAÑADA'],
    ]);
    $registros = [];
    foreach ([1, 2] as $i) {
        $registro = (string) Str::uuid(); $registros[] = $registro;
        DB::table('taxonomia.especimenes')->insert(['id' => $registro, 'taxon_id' => $hoja,
            'codigo_catalogo' => 'QA4-HOJA-'.$i, 'occurrence_id' => 'QA4-HOJA-'.$i, 'colector' => 'Hoja QA4',
            'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5]);
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $registro]);
    }
    $consulta = app(ConsultaCatalogoPublico::class);
    foreach (['géneros' => 3, 'familias' => 2, 'especies' => 3, 'registros' => 6] as $unidad => $total) {
        expect($consulta->responder('¿Cuántos '.$unidad.' hay en toda la colección?')['datos']['total'])->toBe($total);
    }
    foreach (['géneros' => 1, 'familias' => 1, 'especies' => 0, 'registros' => 2] as $unidad => $total) {
        $respuesta = $consulta->responder('¿Cuántos '.$unidad.' hay en esta selección?', seleccionPortal: ['fco' => 'Hoja QA4']);
        expect($respuesta['datos']['total'])->toBe($total)
            ->and(parametrosEnlaceChat($respuesta))->toBe(['fco' => 'Hoja QA4', 'vista' => 'registros'])
            ->and(json_encode($respuesta, JSON_THROW_ON_ERROR))->not->toContain('IDENTIFICACIÓN DAÑADA');
    }
    expect($consulta->responder('¿Qué familias tienen más registros?')['datos']['filas'])
        ->toBe(['Chatformicidae (4 registros)', 'Chathojidae (2 registros)']);

    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', $registros)
        ->update(['genus_visible' => false, 'family_visible' => false]);
    expect($consulta->responder('¿Cuántos géneros hay en toda la colección?')['datos']['total'])->toBe(2)
        ->and($consulta->responder('¿Cuántas familias hay en toda la colección?')['datos']['total'])->toBe(1)
        ->and($consulta->responder('¿Qué familias tienen más registros?')['datos']['filas'])->toBe(['Chatformicidae (4 registros)'])
        ->and($consulta->responder('¿Cuántos registros hay en toda la colección?')['datos']['total'])->toBe(6);
});
