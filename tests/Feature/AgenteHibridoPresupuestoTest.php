<?php

declare(strict_types=1);

use App\Services\IA\AgenteHibrido;
use App\Services\IA\ClienteOpenAI;
use App\Services\IA\PresupuestoIA;
use App\Console\Commands\EstadoPresupuestoIACommand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(\Tests\InfrastructureTestCase::class);

beforeEach(function (): void {
    $this->directorioIA = sys_get_temp_dir().'/hub-ia-'.bin2hex(random_bytes(8));
    mkdir($this->directorioIA, 0700);
    $this->rutaIA = $this->directorioIA.'/presupuesto.json';
    config(['app.env' => 'production', 'app.url' => 'https://labinvepn.org', 'ia.enabled' => true, 'ia.openai_key' => 'clave-ficticia-solo-pruebas',
        'ia.organization' => 'org-prueba', 'ia.project' => 'proj-prueba', 'ia.ledger' => $this->rutaIA,
        'ia.model' => 'gpt-6.1-terra', 'ia.reasoning_effort' => 'low', 'ia.max_output_tokens' => 512, 'cache.default' => 'array',
        'chatbot.use_local_model' => false]);
    Http::preventStrayRequests();
    $this->presupuestoIA = new PresupuestoIA($this->rutaIA);
});

afterEach(function (): void {
    foreach (glob($this->directorioIA.'/*') ?: [] as $archivo) unlink($archivo);
    rmdir($this->directorioIA);
});

function respuestaIA(string $opcion = 'deposito', array $usage = ['input_tokens' => 100, 'output_tokens' => 20]): array
{
    return ['status' => 'completed', 'usage' => $usage,
        'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['opcion' => $opcion])]]]]];
}

function schemaIA(): array
{
    return ['type' => 'object', 'properties' => ['opcion' => ['type' => 'string']], 'required' => ['opcion'], 'additionalProperties' => false];
}

test('el comando de estado no crea crédito ni contacta a OpenAI y la configuración conserva el modelo con tarifa verificada', function (): void {
    $this->artisan(EstadoPresupuestoIACommand::class)->expectsOutput('Qwen: OpenAI bloqueado por falta de un presupuesto privado válido.')->assertSuccessful();
    $configuracion = require base_path('config/ia.php');
    expect($configuracion['model'])->toBe('gpt-6.1-terra')
        ->and($configuracion['reasoning_effort'])->toBe('low');
    expect(is_file($this->rutaIA))->toBeFalse();
});

test('la orientación documental usa la propuesta pero conserva su respuesta aprobada', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'usage' => ['input_tokens' => 100, 'output_tokens' => 20], 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['texto' => 'La carta de justificación explica el motivo del depósito; debe conservar su firma y validación.'])]]]]])]);
    $asistente = new \Modules\GestionPrestamosRecepciones\Infrastructure\Services\AsistenteDocumentalDepositos;
    expect($asistente->responder('Me perdí con esto y no entiendo', []))->toContain('carta de justificación');
    Http::assertSentCount(1);
});

test('el saldo confirmado de 63 dólares deja un techo de 59.85 sin poder reiniciarlo', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    expect($this->presupuestoIA->estado()['limite'])->toBe(59_850_000);
    $id = $this->presupuestoIA->reservar('org-prueba', 'proj-prueba', 1_000_000);
    $this->presupuestoIA->liquidar($id, 700_000);
    expect((new PresupuestoIA($this->rutaIA))->estado()['gastado'])->toBe(700_000);
    expect(fn () => $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 100_000_000, time() + 7200))
        ->toThrow(RuntimeException::class);
});

test('dos workers comparten reservas y no pueden superar el límite', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 1_000_000, time() + 3600);
    $otro = new PresupuestoIA($this->rutaIA);
    expect($this->presupuestoIA->reservar('org-prueba', 'proj-prueba', 900_000))->toBeString()
        ->and($otro->reservar('org-prueba', 'proj-prueba', 50_001))->toBeNull()
        ->and($otro->reservar('org-prueba', 'proj-prueba', 50_000))->toBeString()
        ->and($this->presupuestoIA->reservar('org-prueba', 'proj-prueba', 1))->toBeNull();
});

test('un presupuesto ausente corrupto vencido o ajeno no permite una inferencia', function (string $caso): void {
    if ($caso !== 'ausente') $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    if ($caso === 'corrupto') file_put_contents($this->rutaIA, '{');
    if ($caso === 'vencido') {
        $e = $this->presupuestoIA->estado(); $e['vence'] = time() - 1;
        file_put_contents($this->rutaIA, json_encode($e));
    }
    if ($caso === 'ajeno') config(['ia.project' => 'otro-proyecto']);
    Http::fake();
    expect(app(ClienteOpenAI::class)->estructurar('Clasifica', 'Hola', schemaIA()))->toBeNull();
    Http::assertNothingSent();
})->with(['ausente', 'corrupto', 'vencido', 'ajeno']);

test('la liquidación usa el consumo real y no pide almacenamiento herramientas ni recarga', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(respuestaIA())]);
    expect(app(ClienteOpenAI::class)->estructurar('Clasifica', 'Hola', schemaIA()))->toBe(['opcion' => 'deposito']);
    expect($this->presupuestoIA->estado())->toMatchArray(['gastado' => 200, 'reservas' => []]);
    Http::assertSent(fn ($r): bool => $r->url() === 'https://api.openai.com/v1/responses'
        && $r['model'] === 'gpt-6.1-terra' && $r['reasoning']['effort'] === 'low'
        && $r['store'] === false && $r['service_tier'] === 'default' && ! isset($r['tools'])
        && $r['max_output_tokens'] === 512 && $r->hasHeader('OpenAI-Project', 'proj-prueba'));
    Http::assertSentCount(1);
});

test('el razonamiento low se incluye en el consumo total y el cliente rechaza otro modelo sin gastar', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(respuestaIA(usage: [
        'input_tokens' => 100, 'output_tokens' => 120, 'output_tokens_details' => ['reasoning_tokens' => 100],
    ]))]);
    expect(app(ClienteOpenAI::class)->estructurar('Clasifica', 'Hola', schemaIA()))->toBe(['opcion' => 'deposito'])
        ->and($this->presupuestoIA->estado()['gastado'])->toBe(700);
    config(['ia.model' => 'gpt-5.6-terra']);
    expect(app(ClienteOpenAI::class)->estructurar('Clasifica', 'Hola', schemaIA()))->toBeNull();
    Http::assertSentCount(1);
});

test('el agotamiento bloquea siguientes llamadas aunque se cambie la clave y se vacíe caché', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(['error' => ['code' => 'credit_balance_exhausted']], 429)]);
    $cliente = app(ClienteOpenAI::class);
    expect($cliente->estructurar('Clasifica', 'Hola', schemaIA()))->toBeNull();
    config(['ia.openai_key' => 'otra-clave-ficticia']); Cache::flush();
    expect($cliente->estructurar('Clasifica', 'Hola', schemaIA()))->toBeNull()
        ->and($this->presupuestoIA->estado()['bloqueo'])->toBe('credito_o_limite_agotado');
    Http::assertSentCount(1);
});

test('un timeout conserva la reserva y no reintenta el consumo', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    Http::fake(['https://api.openai.com/v1/responses' => Http::failedConnection()]);
    expect(app(ClienteOpenAI::class)->estructurar('Clasifica', 'Hola', schemaIA()))->toBeNull()
        ->and(count($this->presupuestoIA->estado()['reservas']))->toBe(1);
    Http::assertSentCount(1);
});

test('una respuesta sin consumo verificable bloquea OpenAI y conserva todo lo reservado', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(respuestaIA(usage: []))]);
    expect(app(ClienteOpenAI::class)->estructurar('Clasifica', 'Hola', schemaIA()))->toBeNull()
        ->and($this->presupuestoIA->estado()['bloqueo'])->toBe('consumo_no_verificable')
        ->and(count($this->presupuestoIA->estado()['reservas']))->toBe(1);
});

test('el agente pondera 80 20 y vuelve a Qwen cuando OpenAI no puede responder', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    config(['app.env' => 'production', 'app.url' => 'https://labinvepn.org', 'chatbot.use_local_model' => true,
        'chatbot.model' => 'qwen3:0.6b', 'chatbot.max_model_bytes' => 1_000_000_000]);
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response(respuestaIA('deposito')),
        '*/api/tags' => Http::response(['models' => [['name' => 'qwen3:0.6b', 'size' => 523_000_000]]]),
        '*/api/chat' => Http::response(['message' => ['content' => '"documentos"']]),
    ]);
    $opciones = ['deposito' => 'entregar material', 'documentos' => 'requisitos'];
    expect(app(AgenteHibrido::class)->seleccionar('Necesito ayuda con esto', $opciones))->toBe('deposito');
    $this->presupuestoIA->bloquear('credito_o_limite_agotado');
    expect(app(AgenteHibrido::class)->seleccionar('Necesito ayuda con esto', $opciones))->toBe('documentos')
        ->and(AgenteHibrido::combinar('deposito', 'documentos'))->toBe('deposito')
        ->and(AgenteHibrido::combinar(null, 'documentos'))->toBe('documentos');
    Http::assertSentCount(3);
});

test('el presupuesto ocupado responde inmediatamente sin consumir', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    $lock = fopen($this->rutaIA.'.lock', 'c+b'); flock($lock, LOCK_EX);
    try {
        Http::fake();
        expect(app(ClienteOpenAI::class)->estructurar('Clasifica', 'Hola', schemaIA()))->toBeNull();
        Http::assertNothingSent();
    } finally { flock($lock, LOCK_UN); fclose($lock); }
});

test('OpenAI permanece desactivado fuera de PROD aunque exista clave y saldo', function (string $entorno, string $url): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    config(['app.env' => $entorno, 'app.url' => $url]);
    Http::fake();
    expect(app(AgenteHibrido::class)->conversar('¿Por qué el cielo es azul?'))->toBeNull()
        ->and(app(ClienteOpenAI::class)->estructurar('Clasifica', 'Hola', schemaIA()))->toBeNull()
        ->and($this->presupuestoIA->estado()['reservas'])->toBe([]);
    Http::assertNothingSent();
})->with([['local', 'http://localhost:8000'], ['production', 'https://dev.labinvepn.org'], ['testing', 'https://labinvepn.org']]);

test('los dos chats responden preguntas generales con una sola inferencia y sin cargar Qwen', function (): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed',
        'usage' => ['input_tokens' => 200, 'output_tokens' => 30],
        'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['texto' => 'La dispersión de Rayleigh explica el color azul del cielo.'])]]]]])]);
    $portal = app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\AsistentePortal::class);
    $handler = app(\Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler::class);
    expect($portal->responder('¿Por qué el cielo es azul?', $handler)['texto'])->toContain('Rayleigh');
    $depositos = app(\Modules\GestionPrestamosRecepciones\Infrastructure\Services\AsistenteDocumentalDepositos::class);
    expect($depositos->responder('¿Por qué el cielo es azul?', []))->toContain('Rayleigh');
    Http::assertSentCount(2);
    Http::assertSent(fn ($r): bool => str_contains($r['instructions'], 'cualquier tema') && str_contains($r['instructions'], 'No inventes'));
});

test('sin crédito la conversación general usa Qwen sin llamar otra vez a OpenAI', function (): void {
    config(['chatbot.use_local_model' => true, 'chatbot.model' => 'qwen3:0.6b', 'chatbot.max_model_bytes' => 1_000_000_000]);
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => 'qwen3:0.6b', 'size' => 523_000_000]]]),
        '*/api/chat' => Http::response(['message' => ['content' => 'El cielo es azul por la dispersión de Rayleigh.']]),
    ]);
    expect(app(AgenteHibrido::class)->conversar('¿Por qué el cielo es azul?'))->toContain('Rayleigh');
    Http::assertSentCount(2);
    Http::assertNotSent(fn ($r): bool => str_contains($r->url(), 'openai.com'));
    Http::assertSent(fn ($r): bool => str_ends_with($r->url(), '/api/chat') && $r['options']['num_thread'] === 1);
});

test('el reparto de conversación reserva el veinte por ciento a Qwen sin duplicar inferencias', function (): void {
    config(['chatbot.use_local_model' => true, 'chatbot.model' => 'qwen3:0.6b', 'chatbot.max_model_bytes' => 1_000_000_000]);
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    Http::fake([
        '*/api/tags' => Http::response(['models' => [['name' => 'qwen3:0.6b', 'size' => 523_000_000]]]),
        '*/api/chat' => Http::response(['message' => ['content' => 'La gravedad es una interacción fundamental.']]),
    ]);
    expect(AgenteHibrido::prefiereOpenAI('Explica la gravedad'))->toBeFalse()
        ->and(app(AgenteHibrido::class)->conversar('Explica la gravedad'))->toContain('gravedad');
    Http::assertSentCount(2);
    Http::assertNotSent(fn ($r): bool => str_contains($r->url(), 'openai.com'));
    $remotas = count(array_filter(range(1, 1000), fn ($n) => AgenteHibrido::prefiereOpenAI('Consulta '.$n)));
    expect($remotas)->toBeBetween(750, 850);
});

test('una propuesta inventada o una abstención no se entrega como orientación aprobada', function (string $opcion): void {
    $this->presupuestoIA->inicializar('org-prueba', 'proj-prueba', 63_000_000, time() + 3600);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(respuestaIA($opcion))]);
    expect(app(AgenteHibrido::class)->seleccionar('Ayuda', ['deposito' => 'entregar material']))->toBeNull();
})->with(['ninguna', 'borrar_usuarios']);
