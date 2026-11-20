<?php

declare(strict_types=1);

use App\Services\Security\TurnstileVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as CloudflareRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

uses(Tests\InfrastructureTestCase::class);

beforeEach(function (): void {
    config([
        'services.turnstile.enabled' => true,
        'services.turnstile.secret' => 'secret-for-test',
        'services.turnstile.expected_hostname' => 'labinvepn.org',
        'services.turnstile.expected_action' => 'turnstile-spin-v2',
    ]);
});

test('producción mantiene Turnstile aunque se desactive la variable de entorno', function (string $environment, string $expected): void {
    $script = <<<'PHP'
    require 'vendor/autoload.php';
    $services = require 'config/services.php';
    echo $services['turnstile']['enabled'] ? 'habilitado' : 'deshabilitado';
    PHP;
    $process = new Process([PHP_BINARY, '-r', $script], base_path(), [
        'APP_ENV' => $environment,
        'TURNSTILE_ENABLED' => 'false',
    ]);

    expect($process->mustRun()->getOutput())->toBe($expected);
})->with([
    'producción' => ['production', 'habilitado'],
    'desarrollo local' => ['local', 'deshabilitado'],
]);

test('Siteverify recupera un fallo transitorio sin solicitar otro token', function (array $body, int $status): void {
    Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::sequence()
        ->push($body, $status)
        ->push(['success' => true, 'hostname' => 'labinvepn.org', 'action' => 'turnstile-spin-v2'])]);

    $request = Request::create('/login', 'POST', ['cf-turnstile-response' => 'token-de-prueba']);
    $verifier = app(TurnstileVerifier::class);
    $verifier->validateLogin($request);
    $verifier->validateLogin($request);

    Http::assertSentCount(2);
    $first = Http::recorded()[0][0]->data();
    expect($first['idempotency_key'])->toBeUuid();
    expect($first['response'])->toBe('token-de-prueba');
    expect(Http::recorded()[1][0]->data())->toBe($first);
})->with([
    'respuesta HTTP 503' => [[], 503],
    'respuesta HTTP 408' => [[], 408],
    'error interno de Cloudflare' => [['success' => false, 'error-codes' => ['internal-error']], 200],
]);

test('Siteverify repite una conexión interrumpida y exige un resultado válido', function (): void {
    $attempts = [];
    Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => function (CloudflareRequest $request) use (&$attempts) {
        $attempts[] = $request->data();
        if (count($attempts) === 1) {
            throw new ConnectionException('Conexión interrumpida en la prueba');
        }

        return Http::response(['success' => true, 'hostname' => 'otro.example', 'action' => 'turnstile-spin-v2']);
    }]);

    $request = Request::create('/register', 'POST', ['cf-turnstile-response' => 'token-de-prueba']);
    expect(fn () => app(TurnstileVerifier::class)->validateRegistration($request))->toThrow(ValidationException::class);
    expect($attempts)->toHaveCount(2);
    expect($attempts[1])->toBe($attempts[0]);
});

test('Siteverify rechaza tokens inválidos sin reintentarlos', function (): void {
    Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
        'success' => false, 'error-codes' => ['timeout-or-duplicate'],
    ])]);

    $request = Request::create('/login', 'POST', ['cf-turnstile-response' => 'token-vencido']);
    expect(fn () => app(TurnstileVerifier::class)->validateLogin($request))->toThrow(ValidationException::class);
    Http::assertSentCount(1);
});

test('Siteverify detiene la recuperación después de dos fallos', function (): void {
    Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([], 503)]);

    $request = Request::create('/login', 'POST', ['cf-turnstile-response' => 'token-de-prueba']);
    expect(fn () => app(TurnstileVerifier::class)->validateLogin($request))->toThrow(
        ValidationException::class, 'No fue posible validar que eres una persona.',
    );
    Http::assertSentCount(2);
});
