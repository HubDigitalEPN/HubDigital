<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureInitialPasswordChanged;
use App\Http\Middleware\NormalizeAuthenticationEmail;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Middleware\EnsureOwnsDepositRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['clave_certificado']);
        $middleware->web(append: [
            NormalizeAuthenticationEmail::class,
            EnsureInitialPasswordChanged::class,
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'deposit.owner' => EnsureOwnsDepositRequest::class,
            'ability' => CheckAbilities::class,
            'abilities' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['bootstrap_token', 'clave_certificado']);

        // Las excepciones de un alta pueden contener SQL con hashes o el cuerpo
        // de la petición. Estas rutas no muestran el depurador ni registran el
        // mensaje, el SQL o la traza, incluso si APP_DEBUG está habilitado.
        $exceptions->report(function (Throwable $e): ?bool {
            if (! request()->attributes->get('administracion_sensible')
                && ! request()->attributes->get('credencial_firma_sensible')) {
                return null;
            }

            Log::error(request()->attributes->get('credencial_firma_sensible')
                ? 'Fallo en la operación de firma Java.' : 'Fallo en administración de usuarios.', [
                'exception_type' => $e::class,
                'exception_code' => $e->getCode(),
            ]);

            return false;
        });

        $exceptions->render(function (Throwable $e, Request $request): ?Response {
            if ((! $request->attributes->get('administracion_sensible')
                && ! $request->attributes->get('credencial_firma_sensible')) || $e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $mensaje = match ($status) {
                403 => 'No tienes permiso para acceder a esta sección.',
                404 => 'No encontrado.',
                429 => 'Demasiados intentos. Intenta nuevamente más tarde.',
                default => 'No fue posible completar la solicitud. Intenta nuevamente o solicita asistencia.',
            };

            return $request->expectsJson()
                ? response()->json(['message' => $mensaje], $status)
                : response($mensaje, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
        });

        $exceptions->render(function (DomainException $e, Request $request): ?JsonResponse {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return null;
        });

        $exceptions->render(function (InvalidArgumentException $e, Request $request): ?JsonResponse {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return null;
        });
    })->create();
