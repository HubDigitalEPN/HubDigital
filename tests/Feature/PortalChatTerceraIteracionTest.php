<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConocimientoPortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\DetectorEntidadesChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\EvaluadorCorpusChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\CorpusChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConversacionBasica;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\RankingIntencionesChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use Livewire\Livewire;
use App\Models\User;
use Modules\CatalogoPublico\Presentation\Http\Controllers\AdministrarAsistente;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\TransferenciaConocimientoChat;
use Illuminate\Support\Facades\RateLimiter;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AnaliticaChat;

test('la negación y el contraste cambian la intención elegida', function (): void {
    $chat = app(ConocimientoPortal::class);
    expect($chat->responder('no quiero donar, quiero depositar', registrar: false)['intent'] ?? null)->toBe('deposito')
        ->and($chat->responder('quiero depositar, no donar', registrar: false)['intent'] ?? null)->toBe('deposito')
        ->and($chat->responder('no quiero depositar sino donar', registrar: false)['intent'] ?? null)->toBe('donacion')
        ->and($chat->responder('no necesito requisitos, quiero saber dónde entrego', registrar: false)['intent'] ?? null)->toBe('entrega');
});

test('un nombre científico conserva la grafía de la base y reemplaza el taxón anterior', function (): void {
    $dynastes = (string) Str::uuid();
    $megasoma = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $dynastes, 'nombre_cientifico' => 'Dynastes', 'rango' => 'genero', 'autor' => 'Prueba', 'anio_descripcion' => 2026],
        ['id' => $megasoma, 'nombre_cientifico' => 'Megasoma', 'rango' => 'genero', 'autor' => 'Prueba', 'anio_descripcion' => 2026],
    ]);
    $detector = app(DetectorEntidadesChat::class);
    expect($detector->extraer('¿Tienen dynastes?')['taxon'] ?? null)->toBe('Dynastes')
        ->and($detector->extraer('perdón, quería decir Megasoma')['taxon'] ?? null)->toBe('Megasoma');

    $catalog = app(ConsultaCatalogoPublico::class);
    $result = $catalog->responder('perdón, quería decir Megasoma', ['taxon' => 'Dynastes']);
    expect($result['entidades']['taxon'] ?? null)->toBe('Megasoma')
        ->and($result['texto'] ?? '')->toContain('registros publicados');
});

test('cero resultados públicos no afirma inexistencia biológica', function (): void {
    $result = app(ConsultaCatalogoPublico::class)->responder('¿Cuántos registros de Inexistentius tienen?');
    expect($result['texto'] ?? '')->toContain('No encontré registros publicados')
        ->not->toContain('no existe');
    $withoutHave = app(ConsultaCatalogoPublico::class)->responder('¿Cuántos Inexistentius hay?');
    expect($withoutHave['texto'] ?? '')->toContain('No encontré registros publicados');
});

test('el catálogo combina taxón y geografía visibles, responde por código y conserva el filtro en una referencia', function (): void {
    $phylum = (string) Str::uuid();
    $genus = (string) Str::uuid();
    $species = (string) Str::uuid();
    $specimen = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $phylum, 'nombre_cientifico' => 'Filo QA '.substr($phylum, 0, 8), 'rango' => 'phylum', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => null],
        ['id' => $genus, 'nombre_cientifico' => 'Dynastes', 'rango' => 'genero', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $phylum],
        ['id' => $species, 'nombre_cientifico' => 'Dynastes hercules', 'rango' => 'especie', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $genus],
    ]);
    DB::table('taxonomia.especimenes')->insert([
        'id' => $specimen, 'codigo_catalogo' => 'MEPN-CHAT-881', 'occurrence_id' => 'MEPN-CHAT-881',
        'taxon_id' => $species, 'localidad' => 'Quito', 'locality_name' => 'Quito',
        'state_province' => 'Pichincha', 'country' => 'Ecuador', 'fecha_colecta' => '2026-09-26', 'colector' => 'Prueba',
        'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5,
    ]);
    DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $specimen]);
    $catalog = app(ConsultaCatalogoPublico::class);
    $combined = $catalog->responder('¿Cuántos Dynastes hay de Pichincha?');
    expect($combined['entidades'])->toMatchArray(['taxon' => 'Dynastes', 'provincia' => 'Pichincha'])
        ->and($combined['datos']['total'])->toBe(1);
    $country = $catalog->responder('¿Tienen Dynastes hercules de Ecuador?');
    expect($country['entidades'])->toMatchArray(['taxon' => 'Dynastes hercules', 'pais' => 'Ecuador'])
        ->and($country['datos']['total'])->toBe(1);
    $byCode = $catalog->responder('buscar MEPN-CHAT-881');
    expect($byCode['intent'] ?? null)->toBe('catalogo.code')
        ->and($byCode['datos']['total'] ?? null)->toBe(1);
    $where = $catalog->responder('¿Dónde los encontraron?', ['taxon' => 'Dynastes']);
    expect($where['texto'] ?? '')->toContain('Quito')
        ->and($where['entidades']['taxon'] ?? null)->toBe('Dynastes');
    $typo = $catalog->responder('¿Tienen Dinastes?');
    expect($typo['texto'] ?? '')->toContain('¿Te refieres a Dynastes?')
        ->and($typo['entidades']['taxon'] ?? null)->toBe('Dinastes');
});

test('el corpus separa las tres particiones y la calibración no cambia los pesos publicados', function (): void {
    expect(CorpusChat::tamanos())->toBe(['train' => 170, 'calibration' => 57, 'test' => 31,
        'test_final' => 68, 'challenge' => 28]);
    $before = DB::table('divulgacion.chat_settings')->where('key', 'ranking')->value('value');
    $evaluation = app(EvaluadorCorpusChat::class)->calibrar();
    expect($evaluation['current']['total'])->toBe(57)
        ->and($evaluation['suggested']['total'])->toBe(57)
        ->and($evaluation['current']['per_class'])->toHaveKey('UNKNOWN')
        ->and($evaluation['current']['bins'])->toHaveKey('<0.50')
        ->and($evaluation['combinations'])->toBe(486)
        ->and(DB::table('divulgacion.chat_settings')->where('key', 'ranking')->value('value'))->toBe($before);
});

test('la confianza cae cuando el segundo candidato queda muy cerca', function (): void {
    $ranker = app(RankingIntencionesChat::class);
    $base = ['score' => .86, 'components' => ['exact' => 0, 'alias' => .9, 'keyword' => .8, 'fts' => 0, 'trigram' => 0, 'context' => 0], 'id' => 1];
    $settings = ['thresholds' => config('chatbot.ranking.thresholds'), 'confidence_formula' => config('chatbot.ranking.confidence_formula')];
    $close = $ranker->confianza($settings + ['candidatos' => [$base, ['score' => .84, 'node' => (object) ['parent_id' => null], 'components' => ['alias' => .8, 'context' => 0]]]]);
    $far = $ranker->confianza($settings + ['candidatos' => [$base, ['score' => .35, 'node' => (object) ['parent_id' => null], 'components' => ['alias' => .8, 'context' => 0]]]]);
    expect($close['valor'])->toBeLessThan($far['valor']);
});

test('saludos y agradecimientos variados no se confunden con peticiones de trámites', function (): void {
    $social = app(ConversacionBasica::class);
    expect($social->responder('saludos desde Quito')['intent'])->toBe('saludo')
        ->and($social->responder('hola, me ayudan')['intent'])->toBe('saludo')
        ->and($social->responder('gracias por la información')['fuente'])->toBe('conversacion')
        ->and($social->responder('hola quiero donar'))->toBeNull();
});

test('entradas enormes se rechazan antes de consultar y nueva conversación limpia el contexto', function (): void {
    foreach ([1024, 10240, 102400] as $size) {
        Livewire::test(ChatBotWidget::class)->set('pregunta', str_repeat('x', $size))->call('enviar')
            ->assertSet('pregunta', '')->assertSee('demasiado larga');
    }
    $context = app(ContextoChat::class);
    $context->guardar([], ['node_id' => 7, 'fuente' => 'catalogo', 'entidades' => ['taxon' => 'Dynastes']]);
    expect($context->obtener()['entities']['taxon'])->toBe('Dynastes');
    Livewire::test(ChatBotWidget::class)->call('nuevaConversacion')->assertSet('mensajes', []);
    expect($context->obtener())->toBe([]);
});

test('un borrador no altera la respuesta publicada hasta que curaduría lo publica', function (): void {
    $curador = User::factory()->curador()->create();
    $node = DB::table('divulgacion.chat_nodes')->where('slug', 'contacto')->first();
    $original = $node->answer;
    Livewire::actingAs($curador)->test(AdministrarAsistente::class)
        ->call('edit', $node->id)->set('answer', 'Respuesta revisada para prueba.')
        ->call('save')->assertHasNoErrors()
        ->set('testQuestion', 'correo del laboratorio')->set('testMode', 'draft')->call('test')
        ->assertSet('testResult.answer', 'Respuesta revisada para prueba.');
    expect(DB::table('divulgacion.chat_nodes')->where('id', $node->id)->value('answer'))->toBe($original);
    Livewire::actingAs($curador)->test(AdministrarAsistente::class)->call('publishDraft', $node->id);
    expect(DB::table('divulgacion.chat_nodes')->where('id', $node->id)->value('answer'))->toBe('Respuesta revisada para prueba.')
        ->and(DB::table('divulgacion.chat_node_revisions')->where('node_id', $node->id)->where('event', 'published')->exists())->toBeTrue();
});

test('los pesos guardan versiones restaurables con autor y comentario', function (): void {
    $curador = User::factory()->curador()->create();
    $before = DB::table('divulgacion.chat_settings')->where('key', 'ranking')->value('value');
    Livewire::actingAs($curador)->test(AdministrarAsistente::class)
        ->set('rankingComment', 'Ajuste de prueba')->call('saveRanking')->assertHasNoErrors();
    $version = DB::table('divulgacion.chat_ranking_versions')->orderByDesc('id')->first();
    expect($version->actor_id)->toBe($curador->id)
        ->and($version->comment)->toBe('Ajuste de prueba');
    $oldVersion = DB::table('divulgacion.chat_ranking_versions')->where('settings', $before)->orderBy('id')->first();
    Livewire::actingAs($curador)->test(AdministrarAsistente::class)->call('restoreRanking', $oldVersion->id);
    expect(DB::table('divulgacion.chat_settings')->where('key', 'ranking')->value('value'))->toBe($before);
});

test('la exportación omite datos privados y la importación prepara borradores sin cambiar respuestas públicas', function (): void {
    $transfer = app(TransferenciaConocimientoChat::class);
    $json = $transfer->exportar();
    expect($json)->toContain('hubdigital-chat-knowledge')->not->toContain('chat_metrics_daily')
        ->not->toContain('chat_unmatched')->not->toContain('portal_chat_feedback');
    $preview = $transfer->previsualizar($json);
    expect($preview['nodes'])->toBeGreaterThan(10);
    $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    $data['nodes'][0]['answer'] = 'Texto del archivo importado.';
    $modified = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $original = DB::table('divulgacion.chat_nodes')->where('slug', $data['nodes'][0]['slug'])->value('answer');
    $transfer->importar($modified, (string) User::factory()->curador()->create()->id);
    $id = DB::table('divulgacion.chat_nodes')->where('slug', $data['nodes'][0]['slug'])->value('id');
    expect(DB::table('divulgacion.chat_nodes')->where('id', $id)->value('answer'))->toBe($original)
        ->and(DB::table('divulgacion.chat_node_drafts')->where('node_id', $id)->value('payload'))->toContain('Texto del archivo importado.');
    $data['nodes'][] = $data['nodes'][0];
    expect(fn () => $transfer->previsualizar(json_encode($data, JSON_THROW_ON_ERROR)))->toThrow(InvalidArgumentException::class);
});

test('la importación rechaza tipos JSON inválidos con un error controlado', function (): void {
    $transfer = app(TransferenciaConocimientoChat::class);
    $data = json_decode($transfer->exportar(), true, flags: JSON_THROW_ON_ERROR);
    $data['synonyms'] = ['texto inesperado'];
    expect(fn () => $transfer->previsualizar(json_encode($data, JSON_THROW_ON_ERROR)))
        ->toThrow(InvalidArgumentException::class);

    $data = json_decode($transfer->exportar(), true, flags: JSON_THROW_ON_ERROR);
    $data['nodes'][0]['parent'] = ['slug' => 'invalido'];
    expect(fn () => $transfer->previsualizar(json_encode($data, JSON_THROW_ON_ERROR)))
        ->toThrow(InvalidArgumentException::class);
});

test('una pregunta compuesta usa dos respuestas institucionales publicadas', function (): void {
    $result = app(ConocimientoPortal::class)->responderCompuesta('Quiero hacer un depósito y saber qué documentos necesito');
    expect($result['intent'])->toBe('deposito+documentos')
        ->and($result['texto'])->toContain('iniciar un depósito temporal')
        ->toContain('Adjunta la autorización');
    expect(app(ConocimientoPortal::class)->responderCompuesta('¿Qué documentos necesito?'))->toBeNull();
});

test('dos sesiones conservan entidades independientes y el cambio de tema limpia taxonomía anterior', function (): void {
    $context = app(ContextoChat::class);
    session()->flush();
    $context->guardar([], ['fuente' => 'catalogo', 'entidades' => ['taxon' => 'Dynastes']]);
    $sessionA = session()->all();
    session()->flush();
    $context->guardar([], ['fuente' => 'catalogo', 'entidades' => ['taxon' => 'Megasoma']]);
    $sessionB = session()->all();
    session()->replace($sessionA);
    expect($context->obtener()['entities']['taxon'])->toBe('Dynastes');
    session()->replace($sessionB);
    expect($context->obtener()['entities']['taxon'])->toBe('Megasoma');
    $context->guardar($context->obtener(), ['fuente' => 'conocimiento', 'node_id' => 1]);
    expect($context->obtener()['entities'])->toBe([]);
    session()->replace($sessionA);
    expect($context->obtener()['entities']['taxon'])->toBe('Dynastes');
});

test('el límite de frecuencia corta el procesamiento sin almacenar la pregunta', function (): void {
    $key = 'portal-chat:'.hash('sha256', 'session:'.session()->getId());
    RateLimiter::clear($key);
    for ($i = 0; $i < 30; $i++) RateLimiter::hit($key, 60);
    Livewire::test(ChatBotWidget::class)->set('pregunta', 'consulta de prueba')->call('enviar')
        ->assertSee('Espera un minuto')->assertDontSee('consulta de prueba');
    RateLimiter::clear($key);
});

test('la traza guarda identificadores y métricas sin transcripción ni filtros privados', function (): void {
    $id = app(AnaliticaChat::class)->registrarMensaje([
        'texto' => 'Mi contraseña secreta es ejemplo', 'fuente' => 'catalogo', 'intent' => 'catalogo.count',
        'entidades' => ['codigo' => 'PRIVADO-123'], 'datos' => ['total' => 0],
        'confianza' => 'HIGH', 'confianza_valor' => 0.9,
    ], 12.5);
    $trace = DB::table('divulgacion.chat_trace')->where('message_id', $id)->first();
    expect($trace->session_hash)->toHaveLength(64)
        ->and($trace->intent)->toBe('catalogo.count')
        ->and($trace->public_filters)->toBeNull()
        ->and(json_encode($trace))->not->toContain('contraseña secreta')->not->toContain('PRIVADO-123');
});

test('las preguntas desconocidas sobre credenciales nunca guardan el secreto literal', function (): void {
    app(ConocimientoPortal::class)->responder('mi token es ABC123secretoXYZ para entrar', registrar: true);
    $samples = DB::table('divulgacion.chat_unmatched')->pluck('sample')->implode(' ');
    expect($samples)->not->toContain('ABC123secretoXYZ');
});
