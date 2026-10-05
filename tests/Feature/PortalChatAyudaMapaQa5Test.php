<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ContextoChat;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use Modules\CatalogoPublico\Presentation\Http\Controllers\PortalCatalogo;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);
uses(Tests\Concerns\ColeccionPortalAislada::class);

function seleccionMapaChatQa5(): array
{
    return ['nivel' => 'species', 'taxon' => 'Chatobius alpha', 'fpais' => 'Ecuador', 'fprov' => 'Esmeraldas',
        'fg' => ['Playa de oro'], 'ffd' => '2000-05-01', 'ffh' => '2000-05-05'];
}

test('la pregunta exacta de puntos ausentes excluye material sin coordenadas públicas conservando la selección', function (string $caso, int $total, int $coordenadas, string $texto): void {
    registrosParaContratoChat();
    $seleccion = seleccionMapaChatQa5();
    if ($caso === 'sin coordenadas') {
        DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-4')->update([
            'decimal_latitude' => null, 'decimal_longitude' => null,
        ]);
    } else {
        DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-4')->update([
            'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5,
        ]);
    }
    if ($caso === 'cero espacial') {
        $seleccion += ['flat' => '-1', 'flax' => '0', 'flon' => '-77', 'flox' => '-76'];
    }
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $respuesta = $asistente->responder('¿Por qué no veo puntos en este mapa?', $handler,
        contextoCatalogo: ['codigo' => 'QA3-CHAT-1'], seleccionPortal: $seleccion);

    expect($respuesta['intent'])->toBe('portal.mapa_ayuda')
        ->and($respuesta['datos'])->toBe(['total' => $total, 'con_coordenadas' => $coordenadas])
        ->and($respuesta['texto'])->toContain($texto, 'selección aplicada de la página')
        ->and($respuesta['entidades'])->toBe([])
        ->and(parametrosEnlaceChat($respuesta))->toEqual($seleccion + ['vista' => 'mapa']);

    $control = $asistente->responder('¿Cómo uso el mapa de esta selección?', $handler,
        contextoCatalogo: ['codigo' => 'QA3-CHAT-1'], seleccionPortal: $seleccion);
    expect($control['intent'])->toBe('portal.filtros')
        ->and($control['texto'])->toContain('La selección aplicada se conserva')
        ->and(parametrosEnlaceChat($control))->toEqual($seleccion + ['vista' => 'mapa']);
    $destino = Livewire::withQueryParams(parametrosEnlaceChat($respuesta))->test(PortalCatalogo::class);
    expect(seleccionChatRenderizada($destino))->toEqual($seleccion);
    $destino->assertViewHas('datosMapa', fn (array $datos): bool => (int) $datos['resumen']['registros'] === $total
        && (int) $datos['resumen']['georreferenciados'] === $coordenadas);
})->with([
    'cero espacial' => ['cero espacial', 0, 0, 'No hay registros publicados'],
    'material sin coordenadas excluido' => ['sin coordenadas', 0, 0, 'No hay registros publicados'],
    'uno con coordenadas' => ['con coordenadas', 1, 1, '1 con coordenadas públicas y válidas'],
]);

test('el diagnóstico conserva el filtro de coordenadas y no cuenta coordenadas reservadas', function (bool $soloCoordenadas, bool $visible, int $total, int $coordenadas): void {
    registrosParaContratoChat();
    $id = DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'QA3-CHAT-4')->value('id');
    DB::table('taxonomia.especimenes')->where('id', $id)->update(['decimal_latitude' => -0.5, 'decimal_longitude' => -78.5]);
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $id)->update(['decimal_longitude_visible' => $visible]);
    $seleccion = seleccionMapaChatQa5();
    if ($soloCoordenadas) $seleccion['fgeo'] = '1';
    $respuesta = app(AsistentePortal::class)->responder('¿Por qué no veo puntos en este mapa?', app(ConsultarChatBotHandler::class), seleccionPortal: $seleccion);

    expect($respuesta['datos'])->toBe(['total' => $total, 'con_coordenadas' => $coordenadas])
        ->and(parametrosEnlaceChat($respuesta))->toEqual($seleccion + ['vista' => 'mapa'])
        ->and($respuesta['texto'])->not->toContain('-0.5', '-78.5');
})->with([
    'visible con filtro' => [true, true, 1, 1],
    'longitud reservada' => [false, false, 0, 0],
    'longitud reservada con filtro' => [true, false, 0, 0],
]);

test('una selección vacía prevalece sobre el chat anterior y la ausencia de página conserva su diferencia', function (): void {
    registrosParaContratoChat();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $pregunta = '¿Por qué no veo puntos en este mapa?';
    $contexto = ['codigo' => 'QA3-CHAT-4'];

    $global = $asistente->responder($pregunta, $handler, contextoCatalogo: $contexto, seleccionPortal: []);
    expect($global['datos'])->toBe(['total' => 4, 'con_coordenadas' => 4])
        ->and($global['entidades'])->toBe([])
        ->and(parametrosEnlaceChat($global))->toBe(['vista' => 'mapa']);

    $anterior = $asistente->responder($pregunta, $handler, contextoCatalogo: $contexto);
    expect($anterior['datos'])->toBe(['total' => 1, 'con_coordenadas' => 1])
        ->and($anterior['texto'])->toContain('consulta pública anterior')
        ->and($anterior['entidades'])->toBe($contexto)
        ->and(parametrosEnlaceChat($anterior))->toBe(['vista' => 'mapa', 'fc' => 'QA3-CHAT-4']);

    $sinPagina = $asistente->responder($pregunta, $handler);
    expect($sinPagina['datos'])->toBeNull()
        ->and($sinPagina['texto'])->toContain('No tengo una selección aplicada')
        ->and(parametrosEnlaceChat($sinPagina))->toBe(['vista' => 'mapa']);
});

test('recargar el catálogo e iniciar una conversación nueva conserva la ayuda de puntos ausentes', function (): void {
    registrosParaContratoChat();
    $seleccion = seleccionMapaChatQa5();
    $catalogo = Livewire::withQueryParams($seleccion + ['vista' => 'mapa'])->test(PortalCatalogo::class);
    $chat = Livewire::test(ChatBotWidget::class)->call('nuevaConversacion');
    app(ContextoChat::class)->guardar([], ['fuente' => 'catalogo', 'entidades' => ['codigo' => 'QA3-CHAT-1']]);
    $chat->set('pregunta', '¿Por qué no veo puntos en este mapa?')->call('enviar', seleccionChatRenderizada($catalogo));
    $primera = $chat->get('mensajes')[1];
    expect($primera['texto'])->toContain('Hay 1 registro publicado', '1 con coordenadas públicas y válidas')
        ->and(parametrosEnlaceChat($primera))->toEqual($seleccion + ['vista' => 'mapa'])
        ->and(app(ContextoChat::class)->obtener()['entities'])->toBe([]);

    $catalogoRecargado = Livewire::withQueryParams($seleccion + ['vista' => 'mapa'])->test(PortalCatalogo::class);
    $chat->call('nuevaConversacion')->set('pregunta', '¿Por qué no veo puntos en este mapa?')
        ->call('enviar', seleccionChatRenderizada($catalogoRecargado));
    expect($chat->get('mensajes')[1]['texto'])->toBe($primera['texto'])
        ->and(parametrosEnlaceChat($chat->get('mensajes')[1]))->toEqual($seleccion + ['vista' => 'mapa']);
});

test('un diagnóstico de mapa con criterios inválidos pide corregirlos sin contar otra población', function (): void {
    registrosParaContratoChat();
    $respuesta = app(AsistentePortal::class)->responder('¿Por qué no veo puntos en este mapa?', app(ConsultarChatBotHandler::class),
        contextoCatalogo: ['codigo' => 'QA3-CHAT-1'], seleccionPortal: ['ffd' => '2000-05-05', 'ffh' => '2000-05-01']);
    expect($respuesta['intent'])->toBe('catalogo.aclaracion')
        ->and($respuesta['texto'])->toContain('criterios inválidos', 'no he calculado un diagnóstico parcial')
        ->and($respuesta)->not->toHaveKey('datos');
});
