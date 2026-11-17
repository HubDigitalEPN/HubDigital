<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\DetectorEntidadesChat;
use Modules\CatalogoPublico\Presentation\Http\Controllers\ChatBotWidget;

uses(Tests\DatabaseFeatureTestCase::class, Tests\Concerns\ColeccionPortalAislada::class);

test('las instrucciones de matriz no desplazan un taxon confirmado por un binomio supuesto', function (string $inicio): void {
    registrosParaContratoChat();
    Http::preventStrayRequests();
    $r = app(AsistentePortal::class)->responder($inicio.' una matriz de los registros de Chatobius en Pichincha entre 1999 y 2001. Incluye código, fecha y localidad.', app(ConsultarChatBotHandler::class));
    expect($r['intent'])->toBe('catalogo.matriz_datos')->and($r['datos']['total'])->toBe(3)
        ->and(parametrosEnlaceChat($r))->toMatchArray(['ft' => 'Chatobius', 'fprov' => 'Pichincha', 'ffd' => '1999-01-01', 'ffh' => '2001-12-31']);
    Http::assertNothingSent();
})->with(['Genera', 'Crea', 'Prepara', 'Construye']);

test('la tabla de conteos conserva la consulta anterior y el periodo en el widget', function (): void {
    jerarquiaGenerosChat();
    Http::preventStrayRequests();
    $widget = Livewire::test(ChatBotWidget::class)
        ->set('pregunta', 'Chatobius en Pichincha entre 1999 y 2001 en una matriz de datos')->call('enviar');
    $widget->set('pregunta', 'De esa consulta, ¿cuántos registros hay y cuántas especies distintas? Conserva Chatobius, Pichincha y el periodo 1999 a 2001; presenta los resultados en tabla.')->call('enviar');
    $respuesta = $widget->get('mensajes')[3];
    expect(array_column($respuesta['tabla']['filas'] ?? [], 'unidad'))->toBe(['Registros', 'Especies'])
        ->and(array_column($respuesta['tabla']['filas'], 'cantidad'))->toBe([3, 2]);
    expect(parametrosEnlaceChat($respuesta))->toMatchArray(['ft' => 'Chatobius', 'fprov' => 'Pichincha', 'ffd' => '1999-01-01', 'ffh' => '2001-12-31']);
    Http::assertNothingSent();
});

test('una matriz de seguimiento mantiene el año y el conteo de tres unidades no vuelve a la pagina global', function (): void {
    jerarquiaGenerosChat();
    Http::preventStrayRequests();
    $widget = Livewire::test(ChatBotWidget::class)
        ->set('pregunta', 'Chatobius en Pichincha entre 1999 y 2001 en una matriz de datos')->call('enviar');
    $widget->set('pregunta', 'Y ahora solo los de 2000; conserva el taxón y la provincia de la consulta anterior y muestra la matriz.')->call('enviar', seleccionPortal: []);
    $matriz = $widget->get('mensajes')[3];
    expect($matriz['tabla']['titulo'])->toBe('Matriz de datos públicos');
    $widget->set('pregunta', 'De esos registros, cuenta registros, especies y familias distintas en una tabla, manteniendo los filtros anteriores.')->call('enviar', seleccionPortal: []);
    $r = $widget->get('mensajes')[5];
    expect(array_column($r['tabla']['filas'] ?? [], 'unidad'))->toBe(['Registros', 'Especies', 'Familias'])
        ->and(array_column($r['tabla']['filas'], 'cantidad'))->toBe([3, 2, 1])
        ->and(parametrosEnlaceChat($r))->toMatchArray(['ft' => 'Chatobius', 'fprov' => 'Pichincha', 'ffd' => '2000-01-01', 'ffh' => '2000-12-31']);
    Http::assertNothingSent();
});

test('una preposicion no agrega Para y la mencion explicita del lugar sigue disponible', function (): void {
    registrosParaContratoChat();
    DB::table('taxonomia.especimenes')->where('occurrence_id', 'QA3-CHAT-4')->update(['state_province' => 'Pará', 'locality_name' => 'Pará']);
    Http::preventStrayRequests();
    $detector = app(DetectorEntidadesChat::class);
    expect($detector->extraer('Busca Chatobius para comparar sus registros'))->toMatchArray(['taxon' => 'Chatobius'])
        ->not->toHaveKey('provincia')->not->toHaveKey('localidad');
    expect($detector->extraer('Busca Chatobius en Pará')['provincia'])->toBe('Pará');
    Http::assertNothingSent();
});

test('el diseño experimental no se convierte en una consulta de ejemplares ni hereda filtros', function (string $pregunta): void {
    registrosParaContratoChat();
    config(['chatbot.use_local_model' => false, 'chatbot.public_sources' => false, 'ia.enabled' => false]);
    Http::preventStrayRequests();
    $r = app(AsistentePortal::class)->responder($pregunta, app(ConsultarChatBotHandler::class), contextoCatalogo: ['taxon' => 'Chatobius', 'provincia' => 'Pichincha']);
    expect($r['fuente'])->not->toBe('catalogo')->and($r['texto'])->not->toContain('registros publicados', 'Filtros aplicados')
        ->and($r['entidades'] ?? [])->toBe([]);
    Http::assertNothingSent();
})->with([
    'Diseña un experimento para distinguir correlación y causalidad entre iluminación nocturna y actividad de escarabajos. Incluye controles, réplicas y sesgos; es hipotético.',
    'Explica cómo diseñar un experimento para estudiar la actividad nocturna de escarabajos: control, aleatorización, réplicas y sesgos. Es hipotético y no solicita registros del catálogo.',
]);

test('el diseño experimental llega al agente OpenAI sin enviar filtros heredados como hechos', function (): void {
    registrosParaContratoChat();
    $directorio = storage_path('app/private/chat-experimento-'.bin2hex(random_bytes(5)));
    mkdir($directorio, 0700);
    $ledger = $directorio.'/presupuesto.json';
    config(['app.env' => 'production', 'app.url' => 'https://labinvepn.org', 'ia.enabled' => true,
        'ia.openai_key' => 'clave-ficticia-pruebas', 'ia.organization' => 'org-prueba', 'ia.project' => 'proj-prueba',
        'ia.ledger' => $ledger, 'ia.model' => 'gpt-6.1-terra', 'ia.reasoning_effort' => 'low', 'chatbot.use_local_model' => false]);
    (new App\Services\IA\PresupuestoIA($ledger))->inicializar('org-prueba', 'proj-prueba', 1_000_000, time() + 3600);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed',
        'usage' => ['input_tokens' => 100, 'output_tokens' => 30],
        'output' => [['type' => 'message', 'content' => [['type' => 'output_text',
            'text' => json_encode(['texto' => 'Propuesta hipotética con control, aleatorización y réplicas; no son resultados del laboratorio.'])]]]]])]);
    try {
        $r = app(AsistentePortal::class)->responder('Diseña un experimento para estudiar escarabajos nocturnos con control y réplicas.',
            app(ConsultarChatBotHandler::class), contextoCatalogo: ['taxon' => 'Chatobius', 'provincia' => 'Pichincha']);
        expect($r['fuente'])->toBe('agente_hibrido')->and($r['texto'])->toContain('Propuesta hipotética')->and($r)->not->toHaveKey('tabla');
        Http::assertSent(fn ($peticion): bool => $peticion->url() === 'https://api.openai.com/v1/responses'
            && $peticion['model'] === 'gpt-6.1-terra' && !str_contains($peticion['input'], 'Pichincha'));
        Http::assertSentCount(1);
    } finally {
        foreach (glob($directorio.'/*') as $archivo) unlink($archivo);
        rmdir($directorio);
    }
});

test('la matriz conserva la aclaracion de fechas invalidas y la politica de campos ocultos', function (): void {
    registrosParaContratoChat();
    Http::preventStrayRequests();
    $asistente = app(AsistentePortal::class); $handler = app(ConsultarChatBotHandler::class);
    $r = $asistente->responder('Genera una matriz de Chatobius del periodo 2001 a 1999', $handler);
    expect($r['intent'])->toBe('catalogo.aclaracion')->and($r)->not->toHaveKey('tabla');
    DB::table('divulgacion.especimenes_divulgables')->update(['recorded_by_visible' => false]);
    $r = $asistente->responder('Genera una matriz de Chatobius entre 1999 y 2001', $handler);
    expect($r['datos']['total'])->toBe(4)->and(array_column($r['tabla']['filas'], 'colector'))->toBe([null, null, null, null]);
    Http::assertNothingSent();
});
