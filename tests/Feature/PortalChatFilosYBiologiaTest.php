<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;

uses(Tests\DatabaseFeatureTestCase::class, Tests\Concerns\ColeccionPortalAislada::class);

function coleccionCuatroFilosChat(): void
{
    registrosParaContratoChat();
    DB::table('taxonomia.taxones')->where('rango', 'phylum')->update(['nombre_cientifico' => 'Arthropoda']);
    $modelo = (array) DB::table('taxonomia.especimenes')->first(['localidad', 'fecha_colecta', 'colector', 'country', 'state_province', 'locality_name', 'decimal_latitude', 'decimal_longitude']);
    foreach (['Annelida', 'Mollusca', 'Nematomorpha'] as $nombre) {
        $taxon = (string) Str::uuid(); $id = (string) Str::uuid();
        DB::table('taxonomia.taxones')->insert(['id' => $taxon, 'rango' => 'phylum', 'nombre_cientifico' => $nombre]);
        DB::table('taxonomia.especimenes')->insert(array_replace($modelo, ['id' => $id, 'taxon_id' => $taxon, 'codigo_catalogo' => 'FILO-'.$nombre, 'occurrence_id' => 'FILO-'.$nombre]));
        DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id]);
    }
}

test('las preguntas de las capturas consultan los cuatro filos reales y presentan una tabla', function (string $pregunta): void {
    Http::preventStrayRequests(); coleccionCuatroFilosChat();
    $respuesta = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class));
    expect($respuesta['intent'])->toBe('catalogo.tabla_filos')
        ->and($respuesta['total'])->toBe(7)
        ->and(array_column($respuesta['tabla']['filas'], 'nombre'))->toBe(['Annelida', 'Arthropoda', 'Mollusca', 'Nematomorpha'])
        ->and(array_column($respuesta['tabla']['filas'], 'registros'))->toBe([1, 4, 1, 1]);
    Http::assertNothingSent();
})->with(['clasifica por filos', 'clasifica por flos', 'has una tabla de las cantidades por filos', 'filos', 'cuántos filos tenemos']);

test('la tabla respeta la selección y los permisos públicos', function (): void {
    Http::preventStrayRequests(); coleccionCuatroFilosChat();
    // La publicación es derivada por trigger: sin taxón confirmado el registro
    // queda fuera del catálogo; no se fuerza el booleano calculado.
    DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'FILO-Mollusca')->update(['taxon_id' => null]);
    DB::table('divulgacion.especimenes_divulgables')->whereIn('especimen_id', DB::table('taxonomia.especimenes')->where('codigo_catalogo', 'FILO-Nematomorpha')->select('id'))->update(['scientific_name_visible' => false]);
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $respuesta = $asistente->responder('tabla por filos', $handler);
    expect(array_column($respuesta['rows'], 'nombre'))->toBe(['Annelida', 'Arthropoda']);
    $filtrada = $asistente->responder('clasifica por filos', $handler, seleccionPortal: ['nivel' => 'phylum', 'taxon' => 'Arthropoda']);
    expect($filtrada['rows'])->toBe([['nombre' => 'Arthropoda', 'registros' => 4]])
        ->and($filtrada['texto'])->toContain('selección aplicada');
    Livewire::test(ChatBotWidget::class)->set('pregunta', 'has una tabla de las cantidades por filos')->call('enviar')
        ->assertSeeHtml('<table class="portal-chat-table">')->assertSee('Registros por filo')->assertDontSee('Nematomorpha');
    config(['chatbot.specimen_search' => false]);
    expect($asistente->responder('filos', $handler)['intent'])->toBe('catalogo.aclaracion');
    Http::assertNothingSent();
});

test('la primera consulta coloquial sobre Formicidae responde igual que la segunda', function (): void {
    Http::preventStrayRequests();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $primera = $asistente->responder('muy bien dime que formicidaes', $handler);
    $segunda = $asistente->responder('muy bien dime que formicidaes', $handler, historial: ['muy bien dime que formicidaes']);
    expect($primera['intent'])->toBe('biologia.formicid')
        ->and($segunda['texto'])->toBe($primera['texto'])
        ->and($primera['texto'])->toContain('Formicidae', 'Hymenoptera')->not->toContain('Saludo');
    Http::assertNothingSent();
});

test('contar especies de un filo conserva esa unidad y no entra en la tabla de registros', function (): void {
    Http::preventStrayRequests(); coleccionCuatroFilosChat();
    $respuesta = app(AsistentePortal::class)->responder('cuantas especies hay en el Filo Arthropoda', app(ConsultarChatBotHandler::class));
    expect($respuesta['datos']['total'])->toBe(2)
        ->and($respuesta['intent'])->not->toBe('catalogo.tabla_filos');
    Http::assertNothingSent();
});
