<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;

uses(Tests\DatabaseFeatureTestCase::class);

test('una explicación sobre hormigas no se convierte en un conteo ni hereda Pichincha', function (): void {
    Http::preventStrayRequests();
    $respuesta = app(AsistentePortal::class)->responder(
        'Explícame la función ecológica de las hormigas cortadoras en un bosque.',
        app(ConsultarChatBotHandler::class),
        contextoCatalogo: ['provincia' => 'Pichincha'],
    );

    expect($respuesta['intent'])->toBe('biologia.hormig')
        ->and($respuesta['entidades'])->toBe([])
        ->and($respuesta['texto'])->not->toContain('registros publicados de');
    Http::assertNothingSent();
});

test('una definición general consulta su fuente sin ofrecer un trámite seleccionado por Qwen', function (): void {
    config(['chatbot.use_local_model' => true, 'chatbot.public_sources' => true, 'app.env' => 'production', 'app.url' => 'https://labinvepn.org']);
    Http::fake([
        'https://es.wikipedia.org/w/api.php*' => Http::response(['query' => ['pages' => [[
            'title' => 'Mutualismo', 'extract' => 'Definición enciclopédica de prueba sobre mutualismo.',
            'fullurl' => 'https://es.wikipedia.org/wiki/Mutualismo',
        ]]]]),
        '*' => Http::response([], 500),
    ]);

    $respuesta = app(AsistentePortal::class)->responder('¿Qué es el mutualismo en biología?', app(ConsultarChatBotHandler::class));

    expect($respuesta['fuente'])->toBe('fuente_publica')
        ->and($respuesta['intent'])->toBe('general.enciclopedia')
        ->and($respuesta['texto'])->toContain('Mutualismo');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '127.0.0.1:11434'));
});
