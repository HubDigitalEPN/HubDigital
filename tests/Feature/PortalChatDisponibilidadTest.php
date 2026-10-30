<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;

uses(Tests\DatabaseFeatureTestCase::class);

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
