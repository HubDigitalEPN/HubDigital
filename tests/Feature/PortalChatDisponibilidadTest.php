<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;

uses(Tests\DatabaseFeatureTestCase::class);

test('DEV y localhost normal no habilitan el chatbot aunque CHAT_ENABLED esté activo', function (string $entorno, string $url) {
    config(['app.env' => $entorno, 'app.url' => $url, 'chatbot.enabled' => true]);
    Http::preventStrayRequests();
    $this->get($url.'/')->assertOk()->assertDontSee('id="chat-bot-trigger"', false)
        ->assertDontSee('id="portal-chat-mobile-trigger"', false)->assertDontSee('data-chat-restore', false);
    $this->get($url.'/portal/catalogo?vista=mapa')->assertOk()->assertDontSee('data-chat-restore', false);
    Livewire::test(ChatBotWidget::class)->assertNotFound();
    Http::assertNothingSent();
})->with([['production', 'https://dev.labinvepn.org'], ['local', 'http://localhost:8000'], ['testing', 'https://dev.labinvepn.org']]);

test('PROD publica el chatbot y su interruptor permite apagarlo', function () {
    config(['app.env' => 'production', 'app.url' => 'https://labinvepn.org', 'chatbot.enabled' => true]);
    $this->get('https://labinvepn.org/')->assertOk()->assertSee('id="chat-bot-trigger"', false);
    config(['chatbot.enabled' => false]);
    $this->get('https://labinvepn.org/')->assertOk()->assertDontSee('data-chat-restore', false)->assertDontSee('id="chat-bot-trigger"', false);
});

test('un componente abierto antes de pasar a DEV tampoco acepta nuevas llamadas', function () {
    Http::preventStrayRequests();
    $widget = Livewire::test(ChatBotWidget::class);
    config(['app.env' => 'production', 'app.url' => 'https://dev.labinvepn.org']);
    $widget->call('enviar')->assertNotFound();
    Http::assertNothingSent();
});

test('el asistente se ofrece sin minimizar fuera de explorar la colección', function (string $ruta) {
    $this->get($ruta)->assertOk()
        ->assertSee('id="chat-bot-trigger"', false)
        ->assertSee('data-chat-ocultable="false"', false)
        ->assertDontSee('aria-label="Ocultar también el icono del asistente"', false)
        ->assertDontSee('>Ocultar asistente</button>', false);
})->with(['/', '/el-laboratorio', '/depositos']);

test('el catálogo permite ocultar y conserva esa opción después de responder', function () {
    Http::preventStrayRequests();
    $this->get('/portal/catalogo?vista=mapa')->assertOk()->assertSee('data-chat-ocultable="true"', false);
    Livewire::test(ChatBotWidget::class, ['puedeOcultar' => true])
        ->set('pregunta', 'muy bien dime que formicidaes')->call('enviar')
        ->assertSet('puedeOcultar', true)->assertSee('Formicidae')
        ->assertSee('Ocultar asistente')->call('nuevaConversacion')->assertSee('Ocultar asistente');
    Http::assertNothingSent();
});
