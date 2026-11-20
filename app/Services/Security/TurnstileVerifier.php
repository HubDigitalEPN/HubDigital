<?php

namespace App\Services\Security;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TurnstileVerifier
{
    private const VALIDACION_PETICION = 'hubdigital.turnstile.validacion_peticion';

    /**
     * Valida el token en el servidor antes de comprobar las credenciales.
     */
    public function validateLogin(Request $request): void
    {
        $this->validate($request, 'iniciar sesión');
    }

    /**
     * Valida el alta pública antes de crear la cuenta o emitir eventos.
     */
    public function validateRegistration(Request $request): void
    {
        $this->validate($request, 'crear la cuenta');
    }

    private function validate(Request $request, string $operation): void
    {
        if (! config('services.turnstile.enabled')) {
            return;
        }

        $secret = (string) config('services.turnstile.secret');
        $token = (string) $request->input('cf-turnstile-response');

        if ($secret === '' || $token === '') {
            $this->reject($operation);
        }

        $expectedHostname = (string) config('services.turnstile.expected_hostname');
        $expectedAction = (string) config('services.turnstile.expected_action');
        $huella = hash('sha256', serialize([
            $operation, $token, $secret, $expectedHostname, $expectedAction, $request->ip(),
        ]));
        // Fortify invoca el callback al comprobar 2FA y al iniciar la sesión.
        // Siteverify consume el token: conservar el éxito solo en ESTE Request,
        // nunca en sesión ni en caché compartida entre intentos de ingreso.
        if ($request->attributes->get(self::VALIDACION_PETICION) === $huella) {
            return;
        }

        $result = $this->siteverify([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
            // El mismo intento de acceso puede repetir la consulta a Cloudflare sin consumir otro token.
            'idempotency_key' => (string) Str::uuid(),
        ]);

        if ($result === null) {
            $this->reject($operation, 'No fue posible validar que eres una persona. Intenta nuevamente.');
        }

        if (($result['success'] ?? false) !== true
            || ($expectedHostname !== '' && ($result['hostname'] ?? null) !== $expectedHostname)
            || ($expectedAction !== '' && ($result['action'] ?? null) !== $expectedAction)) {
            $this->reject($operation);
        }

        $request->attributes->set(self::VALIDACION_PETICION, $huella);
    }

    private function siteverify(array $payload): ?array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = Http::asForm()
                    ->acceptJson()
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', $payload);
            } catch (ConnectionException) {
                continue;
            }

            if ($response->serverError() || $response->status() === 408) {
                continue;
            }

            $result = $response->successful() ? $response->json() : null;
            if (is_array($result) && ($result['success'] ?? false) !== true
                && in_array('internal-error', (array) ($result['error-codes'] ?? []), true)) {
                continue;
            }

            return is_array($result) ? $result : null;
        }

        return null;
    }

    private function reject(string $operation, ?string $message = null): never
    {
        throw ValidationException::withMessages([
            'cf-turnstile-response' => $message ?? "Completa la verificación de seguridad antes de {$operation}.",
        ]);
    }
}
