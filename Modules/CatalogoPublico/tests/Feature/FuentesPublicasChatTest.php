<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\FuentesPublicasChat;

uses(Tests\InfrastructureTestCase::class);

test('las fuentes generales ofrecen atribución y reutilizan la consulta sin cargar un modelo', function (): void {
    config()->set('cache.default', 'array');
    config()->set('chatbot.public_sources', true);
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake(['https://es.wikipedia.org/w/api.php*' => Http::response(['query' => ['pages' => [
        ['title' => 'Fotosíntesis', 'index' => 1, 'extract' => 'La fotosíntesis transforma energía luminosa en energía química.', 'fullurl' => 'https://es.wikipedia.org/wiki/Fotos%C3%ADntesis'],
    ]]])]);
    $fuentes = new FuentesPublicasChat;
    $primera = $fuentes->responder('¿Qué es la fotosíntesis?');
    expect($primera['texto'])->toContain('energía luminosa', 'Wikipedia', 'no son datos de la colección')
        ->and($primera['opciones'][0]['url'])->toBe('https://es.wikipedia.org/wiki/Fotos%C3%ADntesis')
        ->and($fuentes->responder('¿Qué es la fotosíntesis?'))->toBe($primera);
    Http::assertSentCount(1);
});

test('una fuente caída o sin extracto ofrece un estado honesto y el cálculo básico no usa la red', function (): void {
    config()->set('cache.default', 'array');
    Cache::flush();
    Http::fake(['*' => Http::response([], 503)]);
    $fuentes = new FuentesPublicasChat;
    expect($fuentes->responder('Tema sin fuente disponible')['intent'])->toBe('general.sin_fuente');
    Http::fake();
    expect($fuentes->responder('¿Cuánto es 12 por 8?')['texto'])->toBe('El resultado es 96.')
        ->and($fuentes->responder('12 entre 0')['texto'])->toBe('No se puede dividir entre cero.')
        ->and($fuentes->responder('2,5 + 0,5')['texto'])->toBe('El resultado es 3.');
    Http::assertNothingSent();
});

test('un pronóstico no se transforma en una película y una fuente ajena al tema se descarta', function (): void {
    config()->set('cache.default', 'array');
    Http::fake(['*' => Http::response(['query' => ['pages' => [
        ['title' => 'Al filo del mañana', 'index' => 1, 'extract' => 'Una película de ciencia ficción.', 'fullurl' => 'https://es.wikipedia.org/wiki/Al_filo_del_ma%C3%B1ana'],
    ]]])]);
    $fuentes = new FuentesPublicasChat;
    expect($fuentes->responder('¿Va a llover mañana en Quito?')['intent'])->toBe('general.sin_actualidad');
    Http::assertNothingSent();
    expect($fuentes->responder('Qué es la estacionalidad ecológica')['intent'])->toBe('general.sin_fuente');
});
