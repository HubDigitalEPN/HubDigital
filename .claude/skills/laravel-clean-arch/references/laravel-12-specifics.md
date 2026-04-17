# Laravel 11/12 Specifics for Clean Architecture

Laravel 11 (Feb 2024) and Laravel 12 (Feb 2025) introduced a slimmer skeleton. Here's how those changes interact with Clean Architecture.

## Table of Contents

1. [The New `bootstrap/app.php`](#1-the-new-bootstrapapphp)
2. [Middleware Registration](#2-middleware-registration)
3. [Exception Handling](#3-exception-handling)
4. [Service Providers (`bootstrap/providers.php`)](#4-service-providers-bootstrapprovidersphp)
5. [Console Commands Auto-Registration](#5-console-commands-auto-registration)
6. [Routing Changes](#6-routing-changes)
7. [Octane + FrankenPHP Considerations](#7-octane--frankenphp-considerations)
8. [Per-Second Rate Limiting](#8-per-second-rate-limiting)

---

## 1. The New `bootstrap/app.php`

Laravel 11+ consolidates what used to be multiple kernel files into a single fluent pipeline:

```php
<?php
// bootstrap/app.php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:     __DIR__.'/../routes/web.php',
        api:     __DIR__.'/../routes/api.php',
        commands:__DIR__.'/../routes/console.php',
        health:  '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->alias([
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Src\Shared\Domain\Exceptions\DomainException $e, $request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $e->code(),
                    'message' => $e->getMessage(),
                ],
            ], 422);
        });
    })
    ->create();
```

### What this means for Clean Architecture

This file is the new **composition root** for the framework layer. Keep it focused on wiring; don't put business logic here.

---

## 2. Middleware Registration

In Laravel 11+, there are **no middleware files in `app/Http/Middleware/`** by default. Register aliases and groups in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    // Append to a group
    $middleware->web(append: [EnsureTenantResolved::class]);

    // Register named aliases
    $middleware->alias([
        'tenant' => EnsureTenantResolved::class,
        'feature' => FeatureFlagMiddleware::class,
    ]);

    // Redirect unauthenticated users
    $middleware->redirectGuestsTo(fn () => route('login'));
})
```

### Custom middleware in Clean Architecture

Put custom middleware in `app/Http/Middleware/` (it's Laravel-specific, so it belongs in `app/`, not `src/`). If the middleware needs domain/application logic, inject a use case:

```php
<?php
namespace App\Http\Middleware;

use Closure;
use Src\Tenancy\Application\UseCases\ResolveTenant\ResolveTenantHandler;
use Src\Tenancy\Application\UseCases\ResolveTenant\ResolveTenantInput;

final class EnsureTenantResolved
{
    public function __construct(private readonly ResolveTenantHandler $handler) {}

    public function handle($request, Closure $next)
    {
        $output = $this->handler->handle(new ResolveTenantInput(
            host: $request->getHost(),
        ));

        app()->scoped('tenant.context', fn() => $output);

        return $next($request);
    }
}
```

---

## 3. Exception Handling

Map your domain exceptions to HTTP responses in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    // Domain exception → 422
    $exceptions->render(function (\Src\Shared\Domain\Exceptions\InvariantViolation $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'invariant_violation', 'message' => $e->getMessage()],
            ], 422);
        }
    });

    // Not-found exception → 404
    $exceptions->render(function (\Src\Shared\Domain\Exceptions\NotFoundException $e, $request) {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'not_found', 'message' => $e->getMessage()],
        ], 404);
    });

    // Conflict (optimistic locking) → 409
    $exceptions->render(function (\Src\Shared\Domain\Exceptions\ConcurrencyException $e) {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'concurrency_conflict', 'message' => $e->getMessage()],
        ], 409);
    });
})
```

### Define a clean hierarchy

```php
<?php
namespace Src\Shared\Domain\Exceptions;

abstract class DomainException extends \RuntimeException
{
    abstract public function code(): string;
}

class InvariantViolation extends DomainException {
    public function code(): string { return 'invariant_violation'; }
}

class NotFoundException extends DomainException {
    public function code(): string { return 'not_found'; }
}

class ConcurrencyException extends DomainException {
    public function code(): string { return 'concurrency_conflict'; }
}
```

---

## 4. Service Providers (`bootstrap/providers.php`)

Laravel 11+ reads providers from `bootstrap/providers.php` (no more `config/app.php`):

```php
<?php
// bootstrap/providers.php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AdmissionsServiceProvider::class,
    App\Providers\BillingServiceProvider::class,
    App\Providers\CatalogServiceProvider::class,
];
```

### Recommended pattern: one provider per bounded context

```php
<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class AdmissionsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        \Src\Admissions\Domain\Repositories\StudentRepository::class
            => \Src\Admissions\Infrastructure\Persistence\Eloquent\Repositories\EloquentStudentRepository::class,

        \Src\Admissions\Application\Ports\NotificationPort::class
            => \Src\Admissions\Infrastructure\Notifications\MailNotificationAdapter::class,

        \Src\Admissions\Application\Ports\EventPublisherPort::class
            => \Src\Shared\Infrastructure\Events\LaravelEventPublisher::class,
    ];

    public function boot(): void
    {
        // Load context-specific routes, migrations, views (if modular)
        $this->loadMigrationsFrom(__DIR__ . '/../../src/Admissions/Infrastructure/Persistence/migrations');
    }
}
```

This keeps each context self-contained and makes it trivial to extract into a package later.

---

## 5. Console Commands Auto-Registration

Laravel 11+ auto-registers anything in `app/Console/Commands/` — no manual kernel registration needed.

For Clean Architecture, the command itself should be an adapter:

```php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Admissions\Application\UseCases\EvaluateAdmission\EvaluateAdmissionHandler;
use Src\Admissions\Application\UseCases\EvaluateAdmission\EvaluateAdmissionInput;

final class EvaluateAdmissionCommand extends Command
{
    protected $signature = 'admissions:evaluate {student} {program}';
    protected $description = 'Evaluate a student admission';

    public function handle(EvaluateAdmissionHandler $handler): int
    {
        $output = $handler->handle(new EvaluateAdmissionInput(
            studentId: $this->argument('student'),
            programCode: $this->argument('program'),
        ));

        $this->info("Status: {$output->status}");
        $this->info($output->admitted ? '✅ Admitted' : '❌ Not admitted');
        return self::SUCCESS;
    }
}
```

Schedule it in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('admissions:evaluate-pending')->dailyAt('02:00');
```

---

## 6. Routing Changes

### Scoped bindings (enforce hierarchy)

```php
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('programs')->group(function () {
    Route::scopeBindings()->group(function () {
        Route::get('/{program}/students/{student}', [AdmissionController::class, 'show']);
    });
});
```

`scopeBindings()` ensures the `{student}` is actually a child of `{program}` — preventing cross-tenant access.

### Custom route binding to domain types

```php
// In a service provider:
Route::bind('studentId', function (string $value) {
    return \Src\Admissions\Domain\ValueObjects\StudentId::fromString($value);
});
```

Then in a controller:

```php
public function show(StudentId $studentId) { /* ... */ }
```

This gives you a typed domain value object right at the HTTP boundary.

---

## 7. Octane + FrankenPHP Considerations

Laravel Octane (especially with FrankenPHP) keeps workers in memory. Clean Architecture **benefits** from this:

- Domain services and handlers are stateless by design → safe to reuse.
- Pure DTOs have no hidden state → no leaks between requests.

But watch out for:

### Container state

```php
// ❌ Bad under Octane: singleton holds per-request state
$this->app->singleton(CurrentUserContextPort::class, SessionUserContext::class);

// ✅ Good: scoped = reset between requests
$this->app->scoped(CurrentUserContextPort::class, SessionUserContext::class);
```

### Static properties in domain

If a domain class caches anything in a static property, it will leak across requests under Octane. **Domain classes should be stateless or per-instance only.**

### Event listeners

Make sure listeners are fresh per event dispatch; avoid storing state on listener instances.

---

## 8. Per-Second Rate Limiting

Laravel 11+ supports per-second rate limiting. Use it to protect use cases:

```php
// In a service provider boot():
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('admissions', function ($request) {
    return Limit::perMinute(60)
        ->by($request->user()?->id ?: $request->ip())
        ->response(fn() => response()->json([
            'success' => false,
            'error' => ['code' => 'rate_limited', 'message' => 'Too many requests'],
        ], 429));
});
```

Apply it:

```php
Route::middleware(['auth:sanctum', 'throttle:admissions'])->group(function () {
    Route::post('/admissions/evaluate', [AdmissionController::class, 'evaluate']);
});
```

---

## Summary

- `bootstrap/app.php` is the new composition root — use fluent builders.
- Custom middleware lives in `app/Http/Middleware/` and can inject use cases.
- Map domain exceptions to HTTP responses in `withExceptions()`.
- Register providers in `bootstrap/providers.php`; prefer **one provider per bounded context**.
- Console commands are auto-registered and should be thin adapters over use cases.
- Prefer `scopeBindings()` for nested routes to enforce hierarchy.
- Under Octane, use `scoped()` not `singleton()` for request-bound state.
- Use per-second rate limiting and domain-aware error responses.
