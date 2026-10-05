<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConocimientoPortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConversacionBasica;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\CorpusChat;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AnaliticaChat;
use Modules\CatalogoPublico\Presentation\Http\Controllers\AdministrarAsistente;

uses(Tests\Concerns\ColeccionPortalAislada::class);

test('las frases sociales no consultan el catálogo y los requisitos ambiguos piden aclaración', function (): void {
    $social = app(ConversacionBasica::class);
    expect($social->responder('muchas gracias')['texto'])->toContain('Con gusto')
        ->and($social->responder('hasta luego')['texto'])->toContain('Hasta luego');

    $knowledge = app(ConocimientoPortal::class);
    $ambiguous = $knowledge->responder('¿Qué requisitos hay?', registrar: false);
    expect($ambiguous['fuente'] ?? null)->toBe('aclaracion')
        ->and($ambiguous['opciones'] ?? [])->toHaveCount(2);

    $depositId = DB::table('divulgacion.chat_nodes')->where('slug', 'deposito')->value('id');
    $donationId = DB::table('divulgacion.chat_nodes')->where('slug', 'donacion')->value('id');
    expect($knowledge->responder('¿Qué requisitos hay?', $depositId, registrar: false)['intent'] ?? null)->toBe('deposito_requisitos')
        ->and($knowledge->responder('¿Qué requisitos hay?', $donationId, registrar: false)['intent'] ?? null)->toBe('donacion_requisitos');
});

test('el catálogo separa taxón y geografía y solo usa campos públicos', function (): void {
    $phylum = (string) Str::uuid();
    $family = (string) Str::uuid();
    $genus = (string) Str::uuid();
    $species = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $phylum, 'nombre_cientifico' => 'Filo QA '.substr($phylum, 0, 8), 'rango' => 'phylum', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => null],
        ['id' => $family, 'nombre_cientifico' => 'Probaticidae', 'rango' => 'familia', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $phylum],
        ['id' => $genus, 'nombre_cientifico' => 'Probaticus', 'rango' => 'genero', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $family],
        ['id' => $species, 'nombre_cientifico' => 'Probaticus exemplaris', 'rango' => 'especie', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $genus],
    ]);
    foreach ([true, false] as $public) {
        $id = (string) Str::uuid();
        DB::table('taxonomia.especimenes')->insert([
            'id' => $id, 'codigo_catalogo' => 'CHAT-SECOND-'.substr($id, 0, 8),
            'occurrence_id' => 'CHAT-SECOND-'.substr($id, 0, 8), 'taxon_id' => $species,
            'localidad' => $public ? 'Quito' : 'Guayaquil',
            'locality_name' => $public ? 'Quito' : 'Guayaquil',
            'state_province' => $public ? 'Pichincha' : 'Guayas',
            'fecha_colecta' => '2026-09-26', 'colector' => 'Prueba',
            'decimal_latitude' => -0.5, 'decimal_longitude' => -78.5,
        ]);
        if ($public) {
            DB::table('divulgacion.especimenes_divulgables')->insert(['id' => (string) Str::uuid(), 'especimen_id' => $id]);
        }
    }
    $catalog = app(ConsultaCatalogoPublico::class);
    expect($catalog->responder('¿Cuántos registros de Probaticus tienen?')['texto'])->toContain('1 registro publicado')
        ->and($catalog->responder('¿Tienen ejemplares de Pichincha?')['texto'])->toContain('1 registro publicado')
        ->and($catalog->responder('¿Cuántos registros tienen de Quito?')['texto'])->toContain('1 registro publicado')
        ->and($catalog->responder('¿Cuántos?', ['taxon' => 'Probaticus'])['texto'])->toContain('1 registro publicado')
        ->and($catalog->responder('¿Qué familias tienen?')['texto'])->toContain('Probaticidae')
        ->and($catalog->responder('¿Qué géneros existen dentro de Probaticidae?')['texto'])->toContain('Probaticus')
        ->and($catalog->responder('¿Cuántos registros hay de Guayaquil?')['texto'] ?? '')->not->toContain('2 registros');
});

test('el probador explica el matching sin sumar uso y curaduría puede ajustar pesos', function (): void {
    $curador = User::factory()->curador()->create();
    $nodeId = DB::table('divulgacion.chat_nodes')->where('slug', 'deposito')->value('id');
    $before = DB::table('divulgacion.chat_nodes')->where('id', $nodeId)->value('uses');
    $diagnosis = app(ConocimientoPortal::class)->diagnosticar('quiero depositar');
    expect($diagnosis['candidatos'][0]['components'])->toHaveKeys(['exact', 'alias', 'fts', 'trigram', 'keyword', 'context', 'transition', 'historical'])
        ->and($diagnosis['confianza'])->toHaveKeys(['valor', 'margen', 'nivel']);
    Livewire::actingAs($curador)->test(AdministrarAsistente::class)
        ->set('testQuestion', 'quiero depositar')->call('test')
        ->assertSet('testResult.node', $diagnosis['candidatos'][0]['slug'])
        ->assertSee('Ver evidencia detallada')
        ->call('saveRanking')->assertHasNoErrors()
        ->set('testQuestion', 'receta de pan de centeno')->call('test')
        ->assertSet('testResult.node', 'UNKNOWN');
    expect(DB::table('divulgacion.chat_nodes')->where('id', $nodeId)->value('uses'))->toBe($before)
        ->and(DB::table('divulgacion.chat_settings')->where('key', 'ranking')->exists())->toBeTrue();
});

test('preguntas desconocidas parecidas se agrupan para revisión sin repetir muestras idénticas', function (): void {
    $knowledge = app(ConocimientoPortal::class);
    $knowledge->responder('receta de pan de centeno', registrar: true);
    $knowledge->responder('receta de pan centeno', registrar: true);
    $rows = DB::table('divulgacion.chat_unmatched')->where('sample', 'like', 'receta de pan%')->get();
    expect($rows->count())->toBeGreaterThanOrEqual(2)
        ->and($rows->pluck('group_key')->unique())->toHaveCount(1);
});

test('el corpus clasifica intenciones difíciles y la confianza media excluye respuestas sin score', function (): void {
    $knowledge = app(ConocimientoPortal::class);
    foreach (CorpusChat::casos() as [$question, $expected]) {
        $result = $knowledge->responder($question, registrar: false);
        expect($result['intent'] ?? null)->toBe($expected === 'ambiguous' ? 'ambiguous' : $expected);
    }

    $before = DB::table('divulgacion.chat_metrics_daily')->where('day', now()->toDateString())->first();
    $analytics = app(AnaliticaChat::class);
    $analytics->registrarMensaje(['fuente' => 'conversacion'], 2.5);
    $analytics->registrarMensaje(['fuente' => 'conocimiento', 'confianza_valor' => .8], 5.0);
    $metrics = DB::table('divulgacion.chat_metrics_daily')->where('day', now()->toDateString())->first();
    expect($metrics->resolved - ($before->resolved ?? 0))->toBe(2)
        ->and($metrics->confidence_observations - ($before->confidence_observations ?? 0))->toBe(1)
        ->and(round((float) $metrics->confidence_sum - (float) ($before->confidence_sum ?? 0), 4))->toBe(.8);
});
