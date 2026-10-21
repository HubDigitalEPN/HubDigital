<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ModeloLocalPequeno;

uses(\Tests\InfrastructureTestCase::class);

test('el modelo local no se invoca por defecto ni si supera un gigabyte', function (): void {
    config()->set('chatbot.use_local_model', false);
    Http::fake();
    expect((new ModeloLocalPequeno)->resumir('pregunta', 'fuente'))->toBeNull();
    Http::assertNothingSent();

    habilitarQwenAislado();
    Http::fake(['*/api/tags' => Http::response(['models' => [['name' => 'qwen3:0.6b', 'size' => 1_000_000_001]]])]);
    expect((new ModeloLocalPequeno)->resumir('pregunta', 'fuente'))->toBeNull();
    Http::assertSentCount(1);
});


test('el modelo pequeno responde solo con una fuente si esta habilitado y bajo el limite', function (): void {
    habilitarQwenAislado();
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => 'qwen3:0.6b', 'size' => 523_000_000]]]),
        '*/api/chat' => Http::response(['message' => ['content' => 'Los insectos tienen tres pares de patas.']]),
    ]);

    expect((new ModeloLocalPequeno)->resumir('¿Cuántas patas tienen los insectos?', 'Los insectos tienen seis patas.'))
        ->toContain('tres pares');
    Http::assertSentCount(2);
});

function habilitarQwenAislado(): void
{
    config()->set('cache.default', 'array');
    config()->set('app.env', 'production');
    config()->set('app.url', 'https://labinvepn.org');
    config()->set('chatbot.use_local_model', true);
    Cache::forget('chat:qwen:inventario:v2:qwen3:0.6b');
    Cache::forget('chat:qwen:inventario:v2:qwen3:4b');
}

test('la corrección de intención conserva negaciones, taxones, citas y códigos y está apagada fuera de producción', function (): void {
    habilitarQwenAislado();
    $servicio = new \Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\OrtografiaConsultaChat;
    $pregunta = 'no quero docuemntos; quiero regsitros de Camponotus atriceps MEPN-INV-123 y "regsitros"';
    expect($servicio->corregir($pregunta))->toBe('no quiero documentos; quiero registros de Camponotus atriceps MEPN-INV-123 y "regsitros"');
    config(['app.url' => 'https://dev.labinvepn.org']);
    expect($servicio->corregir($pregunta))->toBe($pregunta);
    config(['app.url' => 'http://localhost:8000', 'app.env' => 'local']);
    expect($servicio->corregir($pregunta))->toBe($pregunta);
});

test('Qwen de producción recibe contexto acotado y un presupuesto fijo para la interpretación ortográfica', function (): void {
    habilitarQwenAislado();
    config(['chatbot.model' => 'qwen3:4b', 'chatbot.max_model_bytes' => 3_000_000_000]);
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => 'qwen3:4b', 'size' => 2_497_280_480]]]),
        '*/api/chat' => Http::response(['message' => ['content' => '"documentos"']]),
    ]);
    expect((new ModeloLocalPequeno)->seleccionar('y los documntos?', ['documentos' => 'documentación del depósito', 'filtros' => 'filtros del mapa'], ['Quiero entregar ejemplares', str_repeat('x', 300)]))->toBe('documentos');
    Http::assertSent(fn ($r): bool => str_ends_with($r->url(), '/api/chat') && $r['model'] === 'qwen3:4b'
        && $r['options']['num_thread'] === 2 && $r['options']['num_ctx'] === 1024 && $r['options']['num_predict'] === 12
        && str_contains($r['messages'][0]['content'], 'ortografía') && str_contains($r['messages'][0]['content'], 'negaciones')
        && !str_contains($r['messages'][1]['content'], str_repeat('x', 161)));
});

test('la interpretación Qwen queda excluida de localhost y desarrollo aunque se copie la variable', function (string $entorno, string $url): void {
    habilitarQwenAislado();
    config()->set('app.env', $entorno);
    config()->set('app.url', $url);
    Http::fake();
    expect((new ModeloLocalPequeno)->seleccionar('No entiendo este trámite', ['deposito' => 'entrega']))->toBeNull();
    Http::assertNothingSent();
})->with([['local', 'http://localhost:8000'], ['production', 'https://dev.labinvepn.org']]);

test('Qwen sólo devuelve una opción aprobada y reutiliza su interpretación sin otra inferencia', function (): void {
    habilitarQwenAislado();
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => 'qwen3:0.6b', 'size' => 523_000_000]]]),
        '*/api/chat' => Http::response(['message' => ['content' => '"deposito"']]),
    ]);
    $model = new ModeloLocalPequeno;
    $options = ['deposito' => 'entregar especímenes', 'documentos' => 'requisitos'];
    expect($model->seleccionar('Quiero entregar material al museo', $options))->toBe('deposito')
        ->and($model->seleccionar('Quiero entregar material al museo', $options))->toBe('deposito');
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/chat')
        && $request['think'] === false && $request['stream'] === false
        && $request['options']['num_thread'] === 1
        && $request['format']['enum'] === ['deposito', 'documentos', 'ninguna']);
});

test('un fallo o una salida de Qwen fuera de la lista conserva la ayuda habitual', function (string $salida): void {
    habilitarQwenAislado();
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => 'qwen3:0.6b', 'size' => 523_000_000]]]),
        '*/api/chat' => Http::response(['message' => ['content' => $salida]]),
    ]);
    expect((new ModeloLocalPequeno)->seleccionar('Pregunta desconocida', ['firmas' => 'firma digital']))->toBeNull();
})->with(['"aprobar_archivo"', '"ninguna"', 'Archivo aprobado', '{"opcion":"firmas"}']);

test('un modelo ocupado no encola otra inferencia ni libera el lock ajeno', function (): void {
    habilitarQwenAislado();
    Http::fake();
    $occupied = Cache::lock('chat:qwen:inferencia', 10);
    expect($occupied->get())->toBeTrue();
    try {
        expect((new ModeloLocalPequeno)->seleccionar('Otra duda', ['firmas' => 'firma digital']))->toBeNull()
            ->and($occupied->isOwnedByCurrentProcess())->toBeTrue();
        Http::assertNothingSent();
    } finally {
        $occupied->release();
    }
});

test('una caída del servicio libera la inferencia y permite continuar sin modelo', function (): void {
    habilitarQwenAislado();
    Http::fake(['*' => Http::failedConnection()]);
    expect((new ModeloLocalPequeno)->seleccionar('No entiendo el trámite', ['deposito' => 'entrega']))->toBeNull();
    $available = Cache::lock('chat:qwen:inferencia', 10);
    expect($available->get())->toBeTrue();
    $available->release();
});

test('el asistente documental usa la orientación aprobada sin aceptar ni firmar documentos', function (): void {
    habilitarQwenAislado();
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => 'qwen3:0.6b', 'size' => 523_000_000]]]),
        '*/api/chat' => Http::response(['message' => ['content' => '"firma"']]),
    ]);
    $assistant = new \Modules\GestionPrestamosRecepciones\Infrastructure\Services\AsistenteDocumentalDepositos;
    $answer = $assistant->responder('Quiero que el emisor lo avale electrónicamente', [], [], ['solicitud_firmada' => true]);
    expect($answer)->toBe('Si te refieres a «¿Cómo deben estar firmados los PDF?»: '.$assistant->responder('¿Cómo deben estar firmados los PDF?', [], [], ['solicitud_firmada' => true], interpretarConModelo: false))
        ->toContain('no permite superar el control de firmas', 'no recibe certificados');
    Http::assertSentCount(2);
});

test('rúbrica electrónica usa la orientación de firmas sin depender de Qwen', function (): void {
    habilitarQwenAislado();
    Http::fake();
    $assistant = new \Modules\GestionPrestamosRecepciones\Infrastructure\Services\AsistenteDocumentalDepositos;
    expect($assistant->responder('Me piden una rúbrica electrónica, no sé cómo resolverlo', []))
        ->toBe($assistant->responder('¿Cómo deben estar firmados los PDF?', [], interpretarConModelo: false));
    Http::assertNothingSent();
});

test('una consulta global conserva su unidad para y en provincia sin inventar filtros ni usar Qwen', function (): void {
    $context = app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat::class);
    $context->guardar([], ['fuente' => 'catalogo', 'intent' => 'catalogo.count', 'entidades' => []]);
    Http::fake();
    expect($context->obtener()['entities'])->toBe([])
        ->and($context->continuarConsulta('¿Y en Pichincha?'))->toBe('¿Cuántos registros hay en Pichincha?');
    $context->guardar($context->obtener(), ['fuente' => 'catalogo', 'intent' => 'catalogo.species', 'entidades' => []]);
    expect($context->continuarConsulta('¿Y en Orellana?'))->toBe('¿Cuántas especies hay en Orellana?');
    $context->reiniciar();
    expect($context->continuarConsulta('¿Y en Pichincha?'))->toBe('¿Y en Pichincha?');
    Http::assertNothingSent();
});
