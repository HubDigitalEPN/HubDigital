<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\TablaRespuestaCatalogoChat;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;

uses(Tests\DatabaseFeatureTestCase::class, Tests\Concerns\ColeccionPortalAislada::class);

test('consultas públicas de conteo búsqueda especies y comparación generan tablas sin pedirlas', function (string $pregunta) {
    registrosParaContratoChat();
    Http::preventStrayRequests();
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class));
    expect($respuesta['fuente'])->toBe('catalogo')->and($respuesta['tabla']['filas'] ?? [])->not->toBeEmpty();
    Http::assertNothingSent();
})->with(['¿Cuántos registros tienen?', 'Busca Chatobius', '¿Cuántas especies tiene el portal?', '¿Cuántos registros y especies tienen?']);

test('un único registro no exige tabla y las comparaciones conservan todas sus unidades', function () {
    registrosParaContratoChat();
    Http::preventStrayRequests();
    $respuesta = app(AsistentePortal::class)->responder('QA3-CHAT-4', app(ConsultarChatBotHandler::class));
    expect($respuesta['datos']['total'])->toBe(1)->and($respuesta)->not->toHaveKey('tabla');
    $respuesta = app(AsistentePortal::class)->responder('¿Cuántos registros y especies tienen?', app(ConsultarChatBotHandler::class));
    expect(array_column($respuesta['tabla']['filas'], 'unidad'))->toBe(['Registros', 'Especies'])
        ->and(array_column($respuesta['tabla']['filas'], 'cantidad'))->toBe([4, 2]);
    Http::assertNothingSent();
});

test('la tabla general escapa valores y no expone campos no declarados', function () {
    $datos = ['fuente' => 'catalogo', 'datos' => ['total' => 2, 'filas' => [['nombre' => '<script>dato</script>', 'registros' => 2, 'privado' => 'NO-DIVULGAR'], ['nombre' => 'Segundo', 'registros' => 3]]]];
    $respuesta = app(TablaRespuestaCatalogoChat::class)->aplicar($datos);
    expect($respuesta['tabla']['columnas'])->toBe(['nombre' => 'Nombre', 'registros' => 'Registros'])
        ->and(json_encode($respuesta['tabla']))->not->toContain('NO-DIVULGAR');
    Livewire::test(ChatBotWidget::class)->set('mensajes', [['rol' => 'chatbot', 'texto' => 'Resultados', 'tabla' => $respuesta['tabla']]])
        ->assertSeeHtml('&lt;script&gt;dato&lt;/script&gt;')->assertDontSeeHtml('<script>dato</script>')
        ->assertSeeHtml('<th scope="col">Registros</th>')
        ->assertDontSeeHtml('data-grid-frame')->assertDontSeeHtml('data-hub-grid')->assertDontSee('Buscar en esta columna');
});
