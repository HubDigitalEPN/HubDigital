<?php

declare(strict_types=1);

use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConocimientoPortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultaCatalogoPublico;
use Modules\CatalogoPublico\Presentation\Http\Controllers\AdministrarAsistente;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('el asistente entiende saludos, documentos, errores y contexto breve', function (): void {
    $knowledge = app(ConocimientoPortal::class);
    $hello = $knowledge->responder('hola', registrar: false);
    $documents = $knowledge->responder('¿Qué papeles tengo que mandar?', registrar: false);
    $typo = $knowledge->responder('que documetos nesecito para el deposito', registrar: false);
    $deposit = $knowledge->responder('Quiero hacer un depósito', registrar: false);
    $followup = $knowledge->responder('¿Y después?', $deposit['node_id'] ?? null, registrar: false);

    expect($hello['texto'] ?? '')->toContain('Hola')
        ->and($documents['node_id'] ?? null)->toBe($typo['node_id'] ?? null)
        ->and($documents['texto'] ?? '')->toContain('autorización')
        ->and($deposit['node_id'] ?? null)->not->toBeNull()
        ->and($followup['texto'] ?? '')->toContain('curaduría');
});

test('una pregunta ajena al dominio o una inyección no ejecuta SQL arbitrario', function (): void {
    $knowledge = app(ConocimientoPortal::class);
    expect($knowledge->responder('receta de pan de centeno', registrar: false))->toBeNull()
        ->and($knowledge->responder("'; DROP TABLE specimens; --", registrar: false))->toBeNull();
});

test('solo curaduría puede administrar el chat y publicar una respuesta nueva', function (): void {
    $this->get(route('divulgacion.asistente'))->assertRedirect();
    $this->actingAs(User::factory()->depositante()->create())->get(route('divulgacion.asistente'))->assertForbidden();
    $curador = User::factory()->curador()->create();
    $this->actingAs($curador)->get(route('divulgacion.asistente'))->assertOk()->assertSee('Árbol de respuestas');

    Livewire::actingAs($curador)->test(AdministrarAsistente::class)
        ->set('title', 'Consulta de prueba')
        ->set('answer', 'Respuesta institucional comprobada.')
        ->set('aliasesText', 'pregunta de prueba')
        ->call('save')
        ->assertHasNoErrors();

    $nodeId = DB::table('divulgacion.chat_nodes')->where('title', 'Consulta de prueba')->where('status', 'draft')->value('id');
    expect($nodeId)->not->toBeNull();
    Livewire::actingAs($curador)->test(AdministrarAsistente::class)->call('publishDraft', $nodeId);
    expect(DB::table('divulgacion.chat_nodes')->where('id', $nodeId)->value('status'))->toBe('published');
    Livewire::actingAs($curador)->test(AdministrarAsistente::class)
        ->call('edit', $nodeId)
        ->set('variantsText', '2|inactivo|Respuesta de prueba alternativa.')
        ->call('save')
        ->assertHasNoErrors();
    expect(DB::table('divulgacion.chat_variants')->where('node_id', $nodeId)->exists())->toBeFalse();
    Livewire::actingAs($curador)->test(AdministrarAsistente::class)->call('publishDraft', $nodeId);
    expect(DB::table('divulgacion.chat_variants')->where('node_id', $nodeId)->where('text', 'Respuesta de prueba alternativa.')->value('active'))->toBeFalse();
});

test('las consultas científicas cuentan únicamente ejemplares divulgables', function (): void {
    $genus = (string) Str::uuid();
    $species = (string) Str::uuid();
    DB::table('taxonomia.taxones')->insert([
        ['id' => $genus, 'nombre_cientifico' => 'Probatorius', 'rango' => 'genero', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => null],
        ['id' => $species, 'nombre_cientifico' => 'Probatorius exemplaris', 'rango' => 'especie', 'autor' => 'Prueba', 'anio_descripcion' => 2026, 'padre_id' => $genus],
    ]);
    $publicId = (string) Str::uuid();
    $privateId = (string) Str::uuid();
    foreach ([$publicId, $privateId] as $index => $id) {
        DB::table('taxonomia.especimenes')->insert([
            'id' => $id, 'codigo_catalogo' => 'CHAT-QA-'.$index.'-'.substr($id, 0, 8),
            'occurrence_id' => 'CHAT-QA-'.$index.'-'.substr($id, 0, 8),
            'taxon_id' => $species, 'localidad' => 'Prueba', 'fecha_colecta' => '2026-09-26', 'colector' => 'Prueba',
        ]);
    }
    DB::table('divulgacion.especimenes_divulgables')->insert([
        'id' => (string) Str::uuid(), 'especimen_id' => $publicId,
    ]);

    $answer = app(ConsultaCatalogoPublico::class)->responder('¿Cuántos registros de Probatorius tienen?');
    expect($answer['texto'] ?? '')->toContain('1 registro publicado')
        ->not->toContain('2 registros');
    $species = app(ConsultaCatalogoPublico::class)->responder('¿Qué especies del género Probatorius hay?');
    expect($species['texto'] ?? '')->toContain('1 especie publicada')->toContain('Probatorius exemplaris');
    $withoutAccents = app(ConsultaCatalogoPublico::class)->responder('Que especies del genero Probatorius hay?');
    expect($withoutAccents['texto'] ?? '')->toContain('Probatorius exemplaris');
});

test('corpus de preguntas naturales conserva una intención estable', function (): void {
    $corpus = [
        'hola' => 'saludo',
        'buenas tardes' => 'saludo',
        'quiero hacer un depósito' => 'deposito',
        'tengo muestras y quiero entregarlas' => 'ambiguous',
        'deseo donar especímenes' => 'donacion',
        'regalar muestras al laboratorio' => 'donacion',
        '¿Qué documentos necesito?' => 'documentos',
        'que papeles tengo que mandar' => 'documentos',
        'que documetos nesecito para el deposito' => 'documentos',
        'requisitos' => 'ambiguous',
        'ya mandé mis documentos, ¿qué sigue?' => 'revision',
        'estado de mi solicitud' => 'revision',
        'puedo llevarlos mañana' => 'entrega',
        'donde llevo los bichos' => 'entrega',
        'buscar especimenes' => 'catalogo',
        'consultar catálogo' => 'catalogo',
        'iniciar sesión' => 'acceso',
        'correo del laboratorio' => 'contacto',
    ];
    $knowledge = app(ConocimientoPortal::class);
    $correct = 0;
    $top2 = 0;
    $unknown = 0;
    $mismatches = [];
    foreach ($corpus as $question => $expected) {
        $result = $knowledge->responder($question, registrar: false);
        $slug = $result['intent'] ?? null;
        if ($slug !== $expected) {
            $mismatches[] = $question.' → '.($slug ?? 'UNKNOWN').' (esperado '.$expected.')';
        }
        $correct += $slug === $expected ? 1 : 0;
        $altIds = array_column(array_slice($result['alternativas'] ?? [], 0, 1), 'id');
        $altSlugs = DB::table('divulgacion.chat_nodes')->whereIn('id', $altIds)->pluck('slug')->all();
        $top2 += $slug === $expected || in_array($expected, $altSlugs, true) ? 1 : 0;
        $unknown += $result === null ? 1 : 0;
    }
    expect($correct)->toBe(count($corpus), 'Accuracy de intención: '.$correct.'/'.count($corpus).'; '.implode('; ', $mismatches))
        ->and($top2)->toBe(count($corpus))
        ->and($unknown)->toBe(0);
});

test('el feedback solo se contabiliza una vez para una respuesta de la sesión', function (): void {
    $nodeId = DB::table('divulgacion.chat_nodes')->where('slug', 'saludo')->value('id');
    $before = DB::table('divulgacion.chat_nodes')->where('id', $nodeId)->value('helpful');
    $chat = Livewire::test(ChatBotWidget::class)
        ->set('pregunta', 'hola')
        ->call('enviar')
        ->assertSee('¿Te sirvió?')
        ->call('valorar', 1, true)
        ->call('valorar', 1, true);

    expect(DB::table('divulgacion.chat_nodes')->where('id', $nodeId)->value('helpful'))->toBe($before + 1);
});
