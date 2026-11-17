<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\MatrizDatosCatalogoChat;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;

uses(Tests\DatabaseFeatureTestCase::class, Tests\Concerns\ColeccionPortalAislada::class);

test('las peticiones de matriz y las frases de la captura devuelven datos reales sin IA', function (string $pregunta): void {
    registrosParaContratoChat();
    Http::preventStrayRequests();
    expect(app(MatrizDatosCatalogoChat::class)->solicitada($pregunta))->toBeTrue();
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class));
    expect($respuesta['intent'] ?? null)->toBe('catalogo.matriz_datos')
        ->and($respuesta['datos']['total'])->toBe(4)
        ->and(array_column($respuesta['tabla']['filas'], 'codigo'))->toEqualCanonicalizing(['QA3-CHAT-1', 'QA3-CHAT-2', 'QA3-CHAT-3', 'QA3-CHAT-4'])
        ->and(array_column($respuesta['tabla']['filas'], 'nombre'))->toContain('Chatobius alpha', 'Chatobius beta');
    Http::assertNothingSent();
})->with([
    'haz una matriz de datos',
    'has una tabla de la especies de este portal',
    'muestrame los dtaos de las especies en una tabal',
    'dame los datos del catalogo',
    'Muéstrame una matriz de datos del catálogo',
    'Haz una matriz de datos',
]);

test('la matriz mantiene toda la selección aplicada y no toma criterios del borrador ni del chat anterior', function (): void {
    registrosParaContratoChat();
    Http::preventStrayRequests();
    $seleccion = ['ft' => 'Chatobius', 'fprov' => 'Pichincha', 'fmes' => '5', 'flat' => '-1', 'flax' => '0', 'flon' => '-79', 'flox' => '-78'];
    $respuesta = app(AsistentePortal::class)->responder('haz una matriz de datos', app(ConsultarChatBotHandler::class), contextoCatalogo: ['provincia' => 'Esmeraldas'], seleccionPortal: $seleccion);
    expect($respuesta['intent'] ?? null)->toBe('catalogo.matriz_datos')
        ->and($respuesta['datos']['total'])->toBe(2)
        ->and(array_column($respuesta['tabla']['filas'], 'codigo'))->toEqualCanonicalizing(['QA3-CHAT-1', 'QA3-CHAT-2'])
        ->and(parametrosEnlaceChat($respuesta))->toMatchArray($seleccion + ['vista' => 'registros']);
    $global = app(AsistentePortal::class)->responder('haz una matriz del catálogo completo', app(ConsultarChatBotHandler::class), contextoCatalogo: ['provincia' => 'Esmeraldas'], seleccionPortal: $seleccion);
    expect($global['datos']['total'])->toBe(4);
    $consulta = app(AsistentePortal::class)->responder('dame una matriz de Chatobius en Esmeraldas', app(ConsultarChatBotHandler::class), seleccionPortal: $seleccion);
    expect($consulta['datos']['total'])->toBe(1)
        ->and($consulta['tabla']['filas'][0]['codigo'])->toBe('QA3-CHAT-4');
    Http::assertNothingSent();
});

test('la matriz respeta divulgación por campo y omite los registros no publicables', function (): void {
    registrosParaContratoChat();
    Http::preventStrayRequests();
    $ids = DB::table('taxonomia.especimenes')->pluck('id', 'occurrence_id');
    DB::table('divulgacion.especimenes_divulgables')->where('especimen_id', $ids['QA3-CHAT-1'])->update(['occurrence_id_visible' => false, 'event_date_visible' => false, 'recorded_by_visible' => false, 'country_visible' => false, 'state_province_visible' => false, 'locality_name_visible' => false]);
    DB::table('taxonomia.especimenes')->where('id', $ids['QA3-CHAT-4'])->update(['taxon_id' => null]);
    $respuesta = app(AsistentePortal::class)->responder('haz una matriz de datos', app(ConsultarChatBotHandler::class));
    $fila = array_values(array_filter($respuesta['tabla']['filas'] ?? [], static fn (array $fila): bool => $fila['codigo'] === null))[0] ?? null;
    expect($respuesta['datos']['total'] ?? null)->toBe(3)
        ->and($fila)->toMatchArray(['codigo' => null, 'nombre' => 'Chatobius alpha', 'fecha' => null, 'colector' => null, 'pais' => null, 'provincia' => null, 'localidad' => null])
        ->and(json_encode($respuesta['tabla']))->not->toContain('QA3-CHAT-1', 'QA3-CHAT-4', 'Playa de oro');
    Http::assertNothingSent();
});

test('el widget renderiza la matriz con encabezados y celdas escapadas y explica selecciones vacías', function (): void {
    registrosParaContratoChat();
    Http::preventStrayRequests();
    DB::table('taxonomia.especimenes')->where('occurrence_id', 'QA3-CHAT-1')->update(['colector' => '<script>dato</script>']);
    Livewire::test(ChatBotWidget::class)->set('pregunta', 'haz una matriz de datos')->call('enviar')
        ->assertSeeHtml('data-chat-matrix')->assertSee('Matriz de datos públicos')
        ->assertSee('Código')->assertSee('Identificación')->assertSee('Fecha de colecta')->assertSee('Localidad')
        ->assertSee('QA3-CHAT-1')->assertSeeHtml('&lt;script&gt;dato&lt;/script&gt;')->assertDontSeeHtml('<script>dato</script>');
    $respuesta = app(AsistentePortal::class)->responder('haz una matriz de datos de esta selección', app(ConsultarChatBotHandler::class), seleccionPortal: ['fc' => 'INEXISTENTE']);
    expect($respuesta['datos']['total'])->toBe(0)
        ->and($respuesta['tabla']['filas'])->toBe([])
        ->and($respuesta['texto'])->toContain('No hay registros públicos');
    config(['chatbot.specimen_search' => false]);
    expect(app(AsistentePortal::class)->responder('haz una matriz de datos', app(ConsultarChatBotHandler::class))['intent'])->toBe('catalogo.aclaracion');
    Http::assertNothingSent();
});

test('la matriz limita la vista previa a diez filas y mantiene el total y la consulta anterior fuera del catálogo', function (): void {
    registrosParaContratoChat();
    Http::preventStrayRequests();
    $modelo = (array) DB::table('taxonomia.especimenes')->where('occurrence_id', 'QA3-CHAT-1')
        ->first(['taxon_id', 'localidad', 'locality_name', 'fecha_colecta', 'colector', 'country', 'state_province', 'decimal_latitude', 'decimal_longitude']);
    foreach (range(5, 16) as $numero) {
        $id = (string) Illuminate\Support\Str::uuid();
        DB::table('taxonomia.especimenes')->insert(array_replace($modelo, ['id' => $id, 'occurrence_id' => 'QA3-CHAT-'.$numero, 'codigo_catalogo' => 'QA3-CHAT-'.$numero]));
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Illuminate\Support\Str::uuid(), 'especimen_id' => $id]);
    }
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $respuesta = $asistente->responder('haz una matriz de datos', $handler, contextoCatalogo: ['provincia' => 'Pichincha']);
    expect($respuesta['datos']['total'])->toBe(15)->and($respuesta['tabla']['filas'])->toHaveCount(10)
        ->and($respuesta['texto'])->toContain('10 de 15', 'primeros 10')
        ->and(parametrosEnlaceChat($respuesta)['fprov'])->toBe('Pichincha');
    foreach (['¿Cómo descargo la matriz de datos?', '¿Qué es una matriz de datos?', 'pasos para consultar y descargar datos del catálogo'] as $ayuda) {
        expect(app(MatrizDatosCatalogoChat::class)->solicitada($ayuda))->toBeFalse();
    }
    foreach ([['flat' => '-100'], ['fxprov' => 'Pichincha']] as $invalida) {
        expect($asistente->responder('haz una matriz de datos de esta selección', $handler, seleccionPortal: $invalida)['intent'])->toBe('catalogo.aclaracion');
    }
    $conflicto = $asistente->responder('haz una matriz de Chatobius de esta selección en Esmeraldas', $handler, seleccionPortal: ['ft' => 'Chatobius', 'fprov' => 'Pichincha']);
    expect($conflicto['requiere_seleccion'])->toBeTrue()->and($conflicto)->not->toHaveKey('tabla');
    Http::assertNothingSent();
});
