<?php

use Illuminate\Support\Facades\Http;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;

uses(Tests\DatabaseFeatureTestCase::class);

test('el diseño hipotético orienta con controles réplicas y sesgos aunque no haya IA ni fuentes públicas', function () {
    config(['ia.enabled'=>false, 'chatbot.use_local_model'=>false, 'chatbot.public_sources'=>false]);
    Http::preventStrayRequests();
    $r=app(AsistentePortal::class)->responder('Diseña un experimento para distinguir correlación y causalidad entre iluminación nocturna y actividad de escarabajos. Incluye controles, réplicas y sesgos; es hipotético.', app(ConsultarChatBotHandler::class));
    expect($r['intent'])->toBe('general.diseno_experimental')->and($r['texto'])->toContain('hipotético', 'escarabajos', 'iluminación', 'Control', 'azar', 'Réplicas independientes', 'sesgos', 'pseudorreplicación', 'evidencia causal')
        ->not->toContain('No encontré una fuente', 'no puedo', 'no verificar', 'Coleoptera');
    expect($r)->not->toHaveKey('tabla');
    Http::assertNothingSent();
});

test('la solicitud breve de aleatorización no se confunde con una descripción del organismo', function () {
    config(['ia.enabled'=>false, 'chatbot.use_local_model'=>false]);
    Http::preventStrayRequests();
    $r=app(AsistentePortal::class)->responder('Explica en dos frases cómo aleatorizar parcelas para medir la actividad de escarabajos.', app(ConsultarChatBotHandler::class));
    expect($r['intent'])->toBe('general.diseno_experimental')->and($r['texto'])->toContain('asigna al azar', 'control', 'réplicas independientes')->not->toContain('Coleoptera');
    Http::assertNothingSent();
});

test('un fallo del proveedor conserva la orientación experimental y hace una sola petición remota', function () {
    $dir=sys_get_temp_dir().'/chat-experimento-'.bin2hex(random_bytes(8));mkdir($dir,0700);
    try {
        config(['app.env'=>'production', 'app.url'=>'https://labinvepn.org', 'ia.enabled'=>true, 'ia.openai_key'=>'ficticia', 'ia.organization'=>'org-prueba', 'ia.project'=>'proj-prueba', 'ia.ledger'=>$dir.'/presupuesto.json', 'chatbot.use_local_model'=>false]);
        (new App\Services\IA\PresupuestoIA($dir.'/presupuesto.json'))->inicializar('org-prueba','proj-prueba',95_000_000,time()+3600);
        Http::preventStrayRequests();Http::fake(['https://api.openai.com/*'=>Http::response([],503)]);
        $r=app(AsistentePortal::class)->responder('Diseña un experimento para distinguir correlación y causalidad entre iluminación nocturna y actividad de escarabajos. Incluye controles, réplicas y sesgos; es hipotético.',app(ConsultarChatBotHandler::class));
        expect($r['intent'])->toBe('general.diseno_experimental')->and($r['texto'])->toContain('Hipótesis', 'sesgos');
        Http::assertSentCount(1);
    } finally { foreach(glob($dir.'/*')?:[] as $file)unlink($file);rmdir($dir); }
});
