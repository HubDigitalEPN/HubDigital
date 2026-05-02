---
name: laravel-clean-arch
description: Comprehensive guide for implementing Clean Architecture in Laravel applications, combining Uncle Bob's layered architecture (Domain, Application, Infrastructure, Presentation) with Laravel's ecosystem (Eloquent, Service Container, Queues, Events, API Resources). Use this skill whenever the user mentions Clean Architecture, Hexagonal Architecture, Onion Architecture, DDD in Laravel, SOLID principles in PHP/Laravel, dependency injection in Laravel, use cases, actions, repositories, layered architecture, or is refactoring a Laravel application toward a maintainable, testable, framework-agnostic structure. Trigger this skill even when the user doesn't explicitly say "Clean Architecture" but describes wanting to "separate business logic", "make Laravel testable", "decouple from Eloquent", "organize by domain", "use repositories and services", or "structure a Laravel project properly".
origin: Combined from clean-architecture (generic), clean-architecture (TS/SOLID), laravel-patterns (ECC), and laravel-architecture skills
version: 1.0.0
---

# Laravel Clean Architecture

Complete, production-grade guide for implementing Clean Architecture in Laravel 10/11/12 applications. This skill unifies Uncle Bob's Clean Architecture principles with Laravel idioms, giving you a framework-independent domain layer while still leveraging Laravel's powerful ecosystem.

---

## When to Use This Skill

- Building new Laravel applications that must scale and remain maintainable
- Refactoring legacy Laravel apps ("fat controllers", "god models") toward clean layers
- Designing APIs, modular monoliths, or microservices on top of Laravel
- Applying DDD, Hexagonal/Ports-and-Adapters, or Onion Architecture in PHP
- Teaching or reviewing Laravel code under SOLID principles
- Structuring teams around bounded contexts (e.g., Billing, Admissions, Catalog)

---

## Table of Contents

1. [Core Principles](#1-core-principles)
2. [The Four Layers in Laravel](#2-the-four-layers-in-laravel)
3. [Recommended Directory Structure](#3-recommended-directory-structure)
4. [Domain Layer in Depth](#4-domain-layer-in-depth)
5. [Application Layer in Depth](#5-application-layer-in-depth)
6. [Infrastructure Layer in Depth](#6-infrastructure-layer-in-depth)
7. [Presentation Layer in Depth](#7-presentation-layer-in-depth)
8. [Dependency Injection with Laravel's Container](#8-dependency-injection-with-laravels-container)
9. [SOLID Applied to Laravel](#9-solid-applied-to-laravel)
10. [Cross-Cutting Concerns](#10-cross-cutting-concerns)
11. [Testing Strategy per Layer](#11-testing-strategy-per-layer)
12. [Common Anti-Patterns in Laravel](#12-common-anti-patterns-in-laravel)
13. [Migration Path: Legacy → Clean](#13-migration-path-legacy--clean)
14. [Final Checklist](#14-final-checklist)

For deeper dives on specific topics, see the `references/` directory:
- `references/ddd-tactical-patterns.md` — Aggregates, Value Objects, Domain Events
- `references/eloquent-as-infrastructure.md` — How to treat Eloquent without leaking it
- `references/laravel-12-specifics.md` — Laravel 11/12 bootstrap, middleware, providers
- `references/testing-recipes.md` — Pest/PHPUnit templates per layer

---

## 1. Core Principles

### The Dependency Rule

**Source code dependencies must point INWARD.** Inner layers must know **nothing** about outer layers.

```
┌──────────────────────────────────────────────────────────────┐
│  Presentation (HTTP Controllers, CLI, Livewire, Jobs entry)  │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Infrastructure (Eloquent, Redis, Mail, external APIs)  │  │
│  │ ┌──────────────────────────────────────────────────┐   │  │
│  │ │ Application (Use Cases / Actions, DTOs, Ports)   │   │  │
│  │ │ ┌────────────────────────────────────────────┐   │   │  │
│  │ │ │ Domain (Entities, Value Objects, Services) │   │   │  │
│  │ │ └────────────────────────────────────────────┘   │   │  │
│  │ └──────────────────────────────────────────────────┘   │  │
│  └────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
              Dependencies point INWARD only
```

### Five Non-Negotiable Rules

1. **Domain has zero framework imports.** No `Illuminate\*`, no Eloquent, no Carbon in the domain (use `DateTimeImmutable`).
2. **Use cases orchestrate, entities decide.** Business rules live inside entities and domain services, not in controllers or actions.
3. **Depend on abstractions at boundaries.** Application defines ports (interfaces); infrastructure provides adapters.
4. **DTOs cross boundaries, not entities.** Never leak Eloquent models to the presentation layer.
5. **The framework is a detail.** You should be able to swap Laravel for Symfony without touching `src/Domain`.

---

## 2. The Four Layers in Laravel

| Layer | Responsibility | Laravel Equivalents | Allowed Dependencies |
|-------|----------------|---------------------|----------------------|
| **Domain** | Business rules, entities, invariants | Pure PHP — NO Laravel | Only PHP stdlib |
| **Application** | Use cases, orchestration, ports | Action classes, DTOs, interfaces | Domain |
| **Infrastructure** | Persistence, I/O, adapters | Eloquent models, repository impls, Mailables, Jobs | Domain, Application, Laravel |
| **Presentation** | Delivery mechanism | Controllers, Form Requests, Resources, Console commands, Livewire | Application, Laravel |

> ⚠️ Eloquent models belong to **Infrastructure**, not Domain. A Domain `Order` entity and an Eloquent `OrderModel` are two different classes. The repository translates between them.

---

## 3. Recommended Directory Structure

Laravel's default `app/` layout is flat. For Clean Architecture, introduce a `src/` directory (PSR-4 autoloaded) that holds domain and application code, while keeping `app/` for Laravel-specific concerns.

### `composer.json` autoload setup

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/",
      "Src\\": "src/"
    }
  }
}
```

### Full layout

```
project-root/
├── app/                              # Laravel-specific (Presentation + Infra wiring)

│   ├── Console/
│   │   └── Commands/                 # Artisan commands (Presentation)

│   ├── Http/
│   │   ├── Controllers/              # Thin controllers — delegate to use cases

│   │   ├── Middleware/
│   │   ├── Requests/                 # Form requests → DTOs

│   │   └── Resources/                # API Resources (output DTOs)

│   ├── Jobs/                         # Queue workers (Infra adapters)

│   ├── Mail/                         # Mailables (Infra)

│   ├── Notifications/
│   ├── Policies/                     # Authorization

│   └── Providers/                    # Bind ports → adapters

│
├── src/                              # Framework-independent code

│   ├── Shared/                       # Kernel / cross-cutting

│   │   ├── Domain/
│   │   │   ├── ValueObject.php
│   │   │   ├── Entity.php
│   │   │   ├── AggregateRoot.php
│   │   │   ├── DomainEvent.php
│   │   │   └── Exceptions/
│   │   └── Application/
│   │       └── Bus/                  # Command/Query bus interfaces

│   │
│   ├── Admissions/                   # Bounded Context #1

│   │   ├── Domain/
│   │   │   ├── Entities/
│   │   │   │   └── Student.php
│   │   │   ├── ValueObjects/
│   │   │   │   ├── StudentId.php
│   │   │   │   └── CompetencePoints.php
│   │   │   ├── Services/
│   │   │   │   └── AdmissionEvaluator.php
│   │   │   ├── Events/
│   │   │   │   └── StudentAdmitted.php
│   │   │   ├── Repositories/         # INTERFACES only

│   │   │   │   └── StudentRepository.php
│   │   │   └── Exceptions/
│   │   │       └── QuotaExceededException.php
│   │   │
│   │   ├── Application/
│   │   │   ├── UseCases/             # aka Actions

│   │   │   │   ├── EvaluateAdmission/
│   │   │   │   │   ├── EvaluateAdmissionHandler.php
│   │   │   │   │   ├── EvaluateAdmissionInput.php
│   │   │   │   │   └── EvaluateAdmissionOutput.php
│   │   │   │   └── RegisterStudent/
│   │   │   ├── Ports/                # Interfaces for external services

│   │   │   │   ├── GradesGatewayPort.php
│   │   │   │   └── NotificationPort.php
│   │   │   └── DTOs/
│   │   │
│   │   └── Infrastructure/
│   │       ├── Persistence/
│   │       │   └── Eloquent/
│   │       │       ├── Models/
│   │       │       │   └── StudentEloquentModel.php
│   │       │       └── Repositories/
│   │       │           └── EloquentStudentRepository.php
│   │       ├── Gateways/
│   │       │   └── HttpGradesGateway.php
│   │       └── Notifications/
│   │           └── MailNotificationAdapter.php
│   │
│   └── Billing/                      # Bounded Context #2 (same internal layout)

│
├── bootstrap/
│   ├── app.php                       # Laravel 11+ central config

│   └── providers.php
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php
│   ├── web.php
│   └── console.php
└── tests/
    ├── Unit/                         # Domain tests — no Laravel boot

    ├── Integration/                  # Repositories + DB

    └── Feature/                      # HTTP end-to-end

```

### Why bounded contexts?

Each top-level folder under `src/` (`Admissions/`, `Billing/`, `Catalog/`) is a **bounded context** (DDD). It owns its own Domain, Application, and Infrastructure layers. Contexts communicate through domain events or application-level ports — **never** by reaching into each other's domain directly.

---

## 4. Domain Layer in Depth

The Domain layer is **pure PHP**. If you can't run `php src/Admissions/Domain/**/*.php` without Laravel, you've leaked.

### 4.1 Entities

An entity has identity and mutable state governed by invariants.

```php
<?php
// src/Admissions/Domain/Entities/Student.php
declare(strict_types=1);

namespace Src\Admissions\Domain\Entities;

use Src\Admissions\Domain\ValueObjects\StudentId;
use Src\Admissions\Domain\ValueObjects\Email;
use Src\Admissions\Domain\ValueObjects\CompetencePoints;
use Src\Admissions\Domain\Exceptions\InvalidStudentStateException;

final class Student
{
    /** @var list<\Src\Shared\Domain\DomainEvent> */
    private array $recordedEvents = [];

    public function __construct(
        private readonly StudentId $id,
        private Email $email,
        private CompetencePoints $points,
        private StudentStatus $status,
        private readonly \DateTimeImmutable $registeredAt,
    ) {}

    public static function register(StudentId $id, Email $email): self
    {
        $student = new self(
            $id,
            $email,
            CompetencePoints::zero(),
            StudentStatus::Pending,
            new \DateTimeImmutable(),
        );
        $student->recordedEvents[] = new Events\StudentRegistered($id, $email);
        return $student;
    }

    public function addPoints(CompetencePoints $additional): void
    {
        if ($this->status === StudentStatus::Rejected) {
            throw new InvalidStudentStateException('Cannot add points to rejected student');
        }
        $this->points = $this->points->add($additional);
    }

    public function admit(): void
    {
        if (!$this->points->meetsMinimumFor(StudentStatus::Admitted)) {
            throw new InvalidStudentStateException('Insufficient points');
        }
        $this->status = StudentStatus::Admitted;
        $this->recordedEvents[] = new Events\StudentAdmitted($this->id);
    }

    public function id(): StudentId { return $this->id; }
    public function email(): Email { return $this->email; }
    public function points(): CompetencePoints { return $this->points; }
    public function status(): StudentStatus { return $this->status; }

    /** @return list<\Src\Shared\Domain\DomainEvent> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }
}
```

Notice: **no Eloquent, no Carbon, no Illuminate, no database thinking.** Just behavior.

### 4.2 Value Objects

Immutable, compared by value, self-validating.

```php
<?php
// src/Admissions/Domain/ValueObjects/Email.php
declare(strict_types=1);

namespace Src\Admissions\Domain\ValueObjects;

use Src\Admissions\Domain\Exceptions\InvalidEmailException;

final readonly class Email
{
    private function __construct(public string $value) {}

    public static function fromString(string $email): self
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException("Invalid email: {$email}");
        }
        return new self(strtolower(trim($email)));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string { return $this->value; }
}
```

### 4.3 Domain Services

Used when logic doesn't naturally belong to a single entity.

```php
<?php
// src/Admissions/Domain/Services/AdmissionEvaluator.php
declare(strict_types=1);

namespace Src\Admissions\Domain\Services;

use Src\Admissions\Domain\Entities\Student;
use Src\Admissions\Domain\ValueObjects\Quota;

final class AdmissionEvaluator
{
    public function canAdmit(Student $student, Quota $remainingQuota): bool
    {
        return $remainingQuota->hasAvailability()
            && $student->points()->meetsMinimumFor($student->status());
    }
}
```

### 4.4 Repository Interfaces (defined in Domain)

```php
<?php
// src/Admissions/Domain/Repositories/StudentRepository.php
declare(strict_types=1);

namespace Src\Admissions\Domain\Repositories;

use Src\Admissions\Domain\Entities\Student;
use Src\Admissions\Domain\ValueObjects\StudentId;

interface StudentRepository
{
    public function findById(StudentId $id): ?Student;
    public function save(Student $student): void;
    public function nextIdentity(): StudentId;
}
```

The interface lives in the Domain, the implementation in Infrastructure. This is **Dependency Inversion**.

---

## 5. Application Layer in Depth

Use cases are thin orchestrators. Each represents **one** business operation.

### 5.1 Use Case (Action) structure

Three files per use case: Input DTO, Output DTO, Handler.

```php
<?php
// src/Admissions/Application/UseCases/EvaluateAdmission/EvaluateAdmissionInput.php
declare(strict_types=1);

namespace Src\Admissions\Application\UseCases\EvaluateAdmission;

final readonly class EvaluateAdmissionInput
{
    public function __construct(
        public string $studentId,
        public string $programCode,
    ) {}
}
```

```php
<?php
// .../EvaluateAdmissionOutput.php
final readonly class EvaluateAdmissionOutput
{
    public function __construct(
        public string $studentId,
        public string $status,
        public int $points,
        public bool $admitted,
    ) {}
}
```

```php
<?php
// .../EvaluateAdmissionHandler.php
declare(strict_types=1);

namespace Src\Admissions\Application\UseCases\EvaluateAdmission;

use Src\Admissions\Domain\Repositories\StudentRepository;
use Src\Admissions\Domain\Services\AdmissionEvaluator;
use Src\Admissions\Domain\ValueObjects\StudentId;
use Src\Admissions\Application\Ports\QuotaProviderPort;
use Src\Admissions\Application\Ports\EventPublisherPort;
use Src\Admissions\Domain\Exceptions\StudentNotFoundException;

final class EvaluateAdmissionHandler
{
    public function __construct(
        private readonly StudentRepository $students,
        private readonly AdmissionEvaluator $evaluator,
        private readonly QuotaProviderPort $quotas,
        private readonly EventPublisherPort $events,
    ) {}

    public function handle(EvaluateAdmissionInput $input): EvaluateAdmissionOutput
    {
        $student = $this->students->findById(StudentId::fromString($input->studentId))
            ?? throw new StudentNotFoundException($input->studentId);

        $quota = $this->quotas->forProgram($input->programCode);

        if ($this->evaluator->canAdmit($student, $quota)) {
            $student->admit();
            $this->students->save($student);
            foreach ($student->pullEvents() as $event) {
                $this->events->publish($event);
            }
        }

        return new EvaluateAdmissionOutput(
            studentId: (string) $student->id(),
            status: $student->status()->value,
            points: $student->points()->value(),
            admitted: $student->status()->isAdmitted(),
        );
    }
}
```

### 5.2 Ports (Application interfaces)

When a use case needs something external, define a **port** (interface) in the Application layer. Infrastructure provides the adapter.

```php
<?php
// src/Admissions/Application/Ports/NotificationPort.php
declare(strict_types=1);

namespace Src\Admissions\Application\Ports;

use Src\Admissions\Domain\ValueObjects\Email;

interface NotificationPort
{
    public function notifyAdmission(Email $to, string $programName): void;
}
```

### 5.3 Using Laravel Actions (optional integration)

If you use `lorisleiva/laravel-actions`, you can wrap handlers as Actions, but keep the **handler class pure**. The Action is just a Laravel adapter:

```php
<?php
namespace App\Actions\Admissions;

use Lorisleiva\Actions\Concerns\AsAction;
use Src\Admissions\Application\UseCases\EvaluateAdmission\EvaluateAdmissionHandler;
use Src\Admissions\Application\UseCases\EvaluateAdmission\EvaluateAdmissionInput;

final class EvaluateAdmissionAction
{
    use AsAction;

    public function __construct(private EvaluateAdmissionHandler $handler) {}

    public function handle(string $studentId, string $programCode): array
    {
        $output = $this->handler->handle(new EvaluateAdmissionInput($studentId, $programCode));
        return (array) $output;
    }
}
```

---

## 6. Infrastructure Layer in Depth

### 6.1 Eloquent as an Infrastructure Detail

Eloquent is great — but it's **infrastructure**. Keep Eloquent models in `Infrastructure/Persistence/Eloquent/Models/`. They are **data mapping structures**, not domain objects.

```php
<?php
// src/Admissions/Infrastructure/Persistence/Eloquent/Models/StudentEloquentModel.php
declare(strict_types=1);

namespace Src\Admissions\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class StudentEloquentModel extends Model
{
    protected $table = 'students';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'email', 'points', 'status', 'registered_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'registered_at' => 'immutable_datetime',
    ];
}
```

### 6.2 Repository Implementation

```php
<?php
// src/Admissions/Infrastructure/Persistence/Eloquent/Repositories/EloquentStudentRepository.php
declare(strict_types=1);

namespace Src\Admissions\Infrastructure\Persistence\Eloquent\Repositories;

use Src\Admissions\Domain\Entities\Student;
use Src\Admissions\Domain\Repositories\StudentRepository;
use Src\Admissions\Domain\ValueObjects\{StudentId, Email, CompetencePoints, StudentStatus};
use Src\Admissions\Infrastructure\Persistence\Eloquent\Models\StudentEloquentModel;
use Illuminate\Support\Str;

final class EloquentStudentRepository implements StudentRepository
{
    public function findById(StudentId $id): ?Student
    {
        $row = StudentEloquentModel::find((string) $id);
        return $row ? $this->toDomain($row) : null;
    }

    public function save(Student $student): void
    {
        StudentEloquentModel::updateOrCreate(
            ['id' => (string) $student->id()],
            [
                'email'         => (string) $student->email(),
                'points'        => $student->points()->value(),
                'status'        => $student->status()->value,
                'registered_at' => $student->registeredAt(),
            ],
        );
    }

    public function nextIdentity(): StudentId
    {
        return StudentId::fromString((string) Str::uuid());
    }

    private function toDomain(StudentEloquentModel $m): Student
    {
        return Student::reconstitute(
            StudentId::fromString($m->id),
            Email::fromString($m->email),
            CompetencePoints::of($m->points),
            StudentStatus::from($m->status),
            $m->registered_at->toDateTimeImmutable(),
        );
    }
}
```

### 6.3 External Gateway Adapter

```php
<?php
// src/Admissions/Infrastructure/Gateways/HttpGradesGateway.php
declare(strict_types=1);

namespace Src\Admissions\Infrastructure\Gateways;

use Src\Admissions\Application\Ports\GradesGatewayPort;
use Illuminate\Support\Facades\Http;

final class HttpGradesGateway implements GradesGatewayPort
{
    public function __construct(private readonly string $baseUrl) {}

    public function fetchGradesFor(string $studentId): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->retry(3, 200)
            ->get("/students/{$studentId}/grades")
            ->throw();

        return $response->json('data', []);
    }
}
```

### 6.4 Queued Job as Adapter

```php
<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Src\Admissions\Application\UseCases\EvaluateAdmission\{
    EvaluateAdmissionHandler, EvaluateAdmissionInput
};

final class EvaluateAdmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly string $studentId,
        private readonly string $programCode,
    ) {}

    public function handle(EvaluateAdmissionHandler $handler): void
    {
        $handler->handle(new EvaluateAdmissionInput($this->studentId, $this->programCode));
    }
}
```

The job is **thin**: it's just an adapter from Laravel's queue into your use case.

### 6.5 Migration

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->unsignedInteger('points')->default(0);
            $table->string('status', 32)->index();
            $table->timestampTz('registered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
```

---

## 7. Presentation Layer in Depth

### 7.1 Thin Controller

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EvaluateAdmissionRequest;
use App\Http\Resources\AdmissionResource;
use Src\Admissions\Application\UseCases\EvaluateAdmission\EvaluateAdmissionHandler;
use Illuminate\Http\JsonResponse;

final class AdmissionController extends Controller
{
    public function __construct(private readonly EvaluateAdmissionHandler $handler) {}

    public function evaluate(EvaluateAdmissionRequest $request): JsonResponse
    {
        $output = $this->handler->handle($request->toInput());

        return response()->json([
            'success' => true,
            'data'    => AdmissionResource::fromOutput($output),
            'error'   => null,
            'meta'    => null,
        ], 200);
    }
}
```

### 7.2 Form Request → DTO

```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Admissions\Application\UseCases\EvaluateAdmission\EvaluateAdmissionInput;

final class EvaluateAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admissions.evaluate') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id'   => ['required', 'uuid'],
            'program_code' => ['required', 'string', 'max:16'],
        ];
    }

    public function toInput(): EvaluateAdmissionInput
    {
        return new EvaluateAdmissionInput(
            studentId:   $this->validated('student_id'),
            programCode: $this->validated('program_code'),
        );
    }
}
```

### 7.3 API Resource

```php
<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Src\Admissions\Application\UseCases\EvaluateAdmission\EvaluateAdmissionOutput;

final class AdmissionResource extends JsonResource
{
    public static function fromOutput(EvaluateAdmissionOutput $output): array
    {
        return [
            'student_id' => $output->studentId,
            'status'     => $output->status,
            'points'     => $output->points,
            'admitted'   => $output->admitted,
        ];
    }
}
```

### 7.4 Routing

```php
// routes/api.php
use App\Http\Controllers\Api\AdmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('admissions')->group(function () {
    Route::post('/evaluate', [AdmissionController::class, 'evaluate'])
        ->name('admissions.evaluate');
});
```

---

## 8. Dependency Injection with Laravel's Container

All ports must be bound to adapters in a Service Provider.

```php
<?php
// app/Providers/AdmissionsServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Admissions\Domain\Repositories\StudentRepository;
use Src\Admissions\Application\Ports\{
    GradesGatewayPort, NotificationPort, EventPublisherPort, QuotaProviderPort
};
use Src\Admissions\Infrastructure\Persistence\Eloquent\Repositories\EloquentStudentRepository;
use Src\Admissions\Infrastructure\Gateways\HttpGradesGateway;
use Src\Admissions\Infrastructure\Notifications\MailNotificationAdapter;
use Src\Admissions\Infrastructure\Events\LaravelEventPublisher;
use Src\Admissions\Infrastructure\Quotas\DatabaseQuotaProvider;

final class AdmissionsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        StudentRepository::class  => EloquentStudentRepository::class,
        NotificationPort::class   => MailNotificationAdapter::class,
        EventPublisherPort::class => LaravelEventPublisher::class,
        QuotaProviderPort::class  => DatabaseQuotaProvider::class,
    ];

    public function register(): void
    {
        $this->app->when(HttpGradesGateway::class)
            ->needs('$baseUrl')
            ->giveConfig('services.grades.base_url');

        $this->app->bind(GradesGatewayPort::class, HttpGradesGateway::class);
    }
}
```

Register in `bootstrap/providers.php` (Laravel 11+):

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AdmissionsServiceProvider::class,
    App\Providers\BillingServiceProvider::class,
];
```

### Contextual & Scoped Bindings

```php
// Different adapter per context
$this->app->when(UrgentNotificationAction::class)
    ->needs(NotificationPort::class)
    ->give(SmsNotificationAdapter::class);

// Singletons for stateless adapters, scoped for request-bound state
$this->app->singleton(GradesGatewayPort::class, HttpGradesGateway::class);
$this->app->scoped(CurrentUserContextPort::class, SessionUserContext::class);
```

---

## 9. SOLID Applied to Laravel

### S — Single Responsibility

Each class has one reason to change. One use case = one class. Don't pile `store/update/destroy/approve/reject` into a god controller. Use **one controller action per use case** or resource controllers only for pure CRUD.

### O — Open/Closed

Add features by **creating new use cases**, not editing existing ones.

```php
// Instead of modifying EvaluateAdmissionHandler, add:
final class EvaluateSpecialQuotaAdmissionHandler { /* ... */ }
```

### L — Liskov Substitution

Any `StudentRepository` implementation must be interchangeable. A test can use `InMemoryStudentRepository`; production uses `EloquentStudentRepository`. Both honor the same contract.

### I — Interface Segregation

Prefer focused interfaces over fat ones.

```php
// ❌ Bad
interface StudentService {
    public function create(); public function update();
    public function delete(); public function sendEmail();
    public function exportCsv(); public function chargeFee();
}

// ✅ Good
interface StudentWriter { public function save(Student $s): void; }
interface StudentReader { public function findById(StudentId $id): ?Student; }
```

### D — Dependency Inversion

**Always inject interfaces**, never concrete Eloquent models or facades into use cases.

```php
// ❌ Bad: use case depends on Eloquent
final class CreateStudentHandler {
    public function handle($data) {
        return \App\Models\Student::create($data);  // tight coupling
    }
}

// ✅ Good: use case depends on abstraction
final class CreateStudentHandler {
    public function __construct(private StudentRepository $students) {}
    public function handle(CreateStudentInput $in): CreateStudentOutput { /* ... */ }
}
```

---

## 10. Cross-Cutting Concerns

### 10.1 Transactions

Wrap the **use case** in a transaction at the infrastructure boundary, not inside the domain:

```php
// Option A: inside the handler (simple)
public function handle(EvaluateAdmissionInput $input): EvaluateAdmissionOutput
{
    return DB::transaction(function () use ($input) {
        /* ... existing logic ... */
    });
}

// Option B: via a decorator (cleaner for CA)
final class TransactionalHandler
{
    public function __construct(
        private readonly EvaluateAdmissionHandler $inner,
        private readonly TransactionManagerPort $tx,
    ) {}

    public function handle(EvaluateAdmissionInput $input): EvaluateAdmissionOutput
    {
        return $this->tx->run(fn() => $this->inner->handle($input));
    }
}
```

### 10.2 Domain Events

```php
// Domain raises events
$student->admit();  // records StudentAdmitted event

// Handler publishes after save
foreach ($student->pullEvents() as $event) {
    $this->eventPublisher->publish($event);
}

// Infrastructure adapter bridges to Laravel events
final class LaravelEventPublisher implements EventPublisherPort
{
    public function __construct(private \Illuminate\Contracts\Events\Dispatcher $dispatcher) {}

    public function publish(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);  // Laravel listeners can now react
    }
}
```

### 10.3 Caching

Cache **at the repository boundary**, not inside use cases:

```php
final class CachedStudentRepository implements StudentRepository
{
    public function __construct(
        private readonly StudentRepository $inner,
        private readonly \Illuminate\Contracts\Cache\Repository $cache,
    ) {}

    public function findById(StudentId $id): ?Student
    {
        return $this->cache->remember(
            "student:{$id}", 300,
            fn() => $this->inner->findById($id),
        );
    }

    public function save(Student $student): void
    {
        $this->inner->save($student);
        $this->cache->forget("student:{$student->id()}");
    }

    public function nextIdentity(): StudentId { return $this->inner->nextIdentity(); }
}
```

Bind it as the **decoration**:

```php
$this->app->bind(StudentRepository::class, function ($app) {
    return new CachedStudentRepository(
        $app->make(EloquentStudentRepository::class),
        $app->make('cache.store'),
    );
});
```

### 10.4 Authorization

Keep Laravel Policies as the mechanism, but invoke them from the Form Request (presentation) or from a use case via a port — **not** inside entities.

### 10.5 Validation

Two layers:
- **Syntactic / HTTP validation** → Form Request (presentation)
- **Business invariants** → Value Objects & Entity constructors (domain)

---

## 11. Testing Strategy per Layer

```
tests/
├── Unit/                 # Pure PHP, milliseconds, NO Laravel boot

│   ├── Domain/           # Entities, VOs, Domain Services

│   └── Application/      # Handlers with in-memory doubles

├── Integration/          # Laravel booted, real DB (SQLite/Postgres)

│   └── Infrastructure/   # Repositories, Gateways, Adapters

└── Feature/              # Full HTTP stack

    └── Api/
```

### 11.1 Domain unit test (Pest)

```php
<?php
// tests/Unit/Domain/StudentTest.php
use Src\Admissions\Domain\Entities\Student;
use Src\Admissions\Domain\ValueObjects\{StudentId, Email, CompetencePoints};
use Src\Admissions\Domain\Exceptions\InvalidStudentStateException;

it('admits a student that meets minimum points', function () {
    $student = Student::register(StudentId::fromString('uuid-1'), Email::fromString('a@b.c'));
    $student->addPoints(CompetencePoints::of(85));

    $student->admit();

    expect($student->status()->isAdmitted())->toBeTrue();
});

it('rejects admission when points are insufficient', function () {
    $student = Student::register(StudentId::fromString('uuid-1'), Email::fromString('a@b.c'));
    $student->addPoints(CompetencePoints::of(10));

    expect(fn() => $student->admit())->toThrow(InvalidStudentStateException::class);
});
```

### 11.2 Application test with in-memory repository

```php
<?php
final class InMemoryStudentRepository implements StudentRepository
{
    /** @var array<string, Student> */ private array $data = [];
    public function findById(StudentId $id): ?Student { return $this->data[(string)$id] ?? null; }
    public function save(Student $s): void { $this->data[(string)$s->id()] = $s; }
    public function nextIdentity(): StudentId { return StudentId::fromString(bin2hex(random_bytes(16))); }
}

it('evaluates admission and emits event', function () {
    $repo = new InMemoryStudentRepository();
    $events = new class implements EventPublisherPort {
        public array $published = [];
        public function publish(DomainEvent $e): void { $this->published[] = $e; }
    };
    // ... set up a student, call the handler, assert output + events
});
```

### 11.3 Integration test (repository)

```php
<?php
uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('persists and reconstitutes a Student aggregate', function () {
    $repo = app(EloquentStudentRepository::class);
    $s = Student::register($repo->nextIdentity(), Email::fromString('x@y.z'));
    $s->addPoints(CompetencePoints::of(50));

    $repo->save($s);
    $loaded = $repo->findById($s->id());

    expect($loaded?->points()->value())->toBe(50);
});
```

### 11.4 Feature / HTTP test

```php
<?php
it('evaluates admission via API', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['admissions.evaluate']);

    $response = postJson('/api/admissions/evaluate', [
        'student_id' => 'uuid-1',
        'program_code' => 'CS101',
    ]);

    $response->assertOk()->assertJsonPath('data.admitted', true);
});
```

---

## 12. Common Anti-Patterns in Laravel

### 12.1 Fat Controller

```php
// ❌ Bad
public function store(Request $r) {
    $data = $r->validate([...]);
    if (User::where('email', $data['email'])->exists()) { return response()->json(...); }
    DB::beginTransaction();
    $user = User::create(...);
    Mail::to($user)->send(new Welcome());
    DB::commit();
    return UserResource::make($user);
}

// ✅ Good
public function store(StoreUserRequest $r): JsonResponse {
    $output = $this->createUser->handle($r->toInput());
    return response()->json(UserResource::fromOutput($output), 201);
}
```

### 12.2 Anemic Eloquent Model as "Domain"

Eloquent models are data access. **Creating `->admit()` on an Eloquent model couples your domain to the framework forever.** Put behavior on domain entities; let Eloquent models stay dumb.

### 12.3 Facades Inside Use Cases

```php
// ❌ Bad: impossible to unit test without booting Laravel
public function handle($input) {
    \Cache::remember(...);
    \Mail::send(...);
}

// ✅ Good: inject ports
public function __construct(private CachePort $cache, private MailPort $mail) {}
```

### 12.4 Returning Eloquent Models from Use Cases

Use Cases must return **DTOs**, never Eloquent models. Otherwise presentation becomes coupled to persistence.

### 12.5 Domain Events Inside Entities That Touch Laravel

`event(new StudentAdmitted(...))` inside the entity couples Domain to Laravel. Instead, **record** events in the entity and let the application/infrastructure dispatch them.

### 12.6 Using `Carbon` in Domain

`Carbon` is a Laravel dependency. Use `DateTimeImmutable` in the Domain; convert at the Infrastructure boundary.

---

## 13. Migration Path: Legacy → Clean

A pragmatic, incremental refactoring (apply over sprints, one bounded context at a time):

1. **Identify bounded contexts** in your codebase (Auth, Billing, Catalog…).
2. **Pick one slice** (e.g., "Create Order") to refactor end-to-end.
3. **Extract a domain entity** from the Eloquent model — put it in `src/<Context>/Domain/Entities/`.
4. **Define the repository interface** in Domain; create an Eloquent-backed implementation in Infrastructure.
5. **Create the use case** (Handler + Input + Output) in Application.
6. **Slim down the controller** to delegate to the handler.
7. **Convert request validation** to Form Requests producing DTOs.
8. **Convert response** via API Resource from the Output DTO.
9. **Write unit tests** for domain + application, integration for infra, feature for HTTP.
10. **Bind ports → adapters** in a dedicated ServiceProvider.
11. **Repeat** for the next slice, then the next context.

Don't try to refactor everything at once. Coexistence is fine: legacy code and clean slices can live in the same app during migration.

---

## 14. Final Checklist

Before marking a feature "done", verify:

- [ ] Domain classes have zero `Illuminate\*` imports
- [ ] Business rules live in entities/value objects, not controllers/actions
- [ ] Every external dependency of a use case is injected as an interface (port)
- [ ] Ports are defined in Application/Domain; adapters in Infrastructure
- [ ] Repository interface in Domain, Eloquent implementation in Infrastructure
- [ ] Controller ≤ 10 lines per action — only maps Request → DTO → Handler → Resource
- [ ] Form Request produces a DTO via `toInput()`
- [ ] API Resource builds from Output DTO, not from Eloquent model
- [ ] Use case returns a DTO, not an Eloquent model
- [ ] Service provider binds all ports to adapters
- [ ] Domain + Application tests run without booting Laravel
- [ ] Eloquent model lives in `Infrastructure/Persistence/Eloquent/Models/`
- [ ] Value objects validate invariants in their constructors
- [ ] Transactions wrap the use case, not individual entity methods
- [ ] Domain events are recorded in entities and dispatched by the handler
- [ ] No Facades inside use cases or domain services
- [ ] Migrations, factories, seeders exist for every persistence change

---

## Quick Reference Card

| I need to… | Put it in… |
|------------|------------|
| Validate business rule | Domain entity or value object |
| Orchestrate a user story | Application use case (handler) |
| Save/load data | Infrastructure repository implementing a Domain interface |
| Call a 3rd-party API | Infrastructure gateway implementing an Application port |
| Handle HTTP input | Presentation: Form Request → DTO |
| Shape HTTP output | Presentation: API Resource from Output DTO |
| Send an email | Application port + Infrastructure Mailable adapter |
| Schedule background work | Infrastructure Job that invokes a Use Case |
| Cache a read | Decorator repository in Infrastructure |
| Publish a domain event | Record in entity, dispatch in handler via port |

---

## References

See the `references/` directory for deeper coverage:

- **`references/ddd-tactical-patterns.md`** — Aggregates, Entity vs. Value Object deep dive, Domain Events patterns, Specification pattern
- **`references/eloquent-as-infrastructure.md`** — Mapping strategies (active record vs. data mapper), avoiding leaks, handling relations across aggregates
- **`references/laravel-12-specifics.md`** — Laravel 11/12 `bootstrap/app.php`, middleware registration, provider auto-discovery, Octane considerations
- **`references/testing-recipes.md`** — Pest templates, factories for domain objects, test doubles (in-memory repositories, fake ports)

---

**Golden rule:** If you can delete Laravel from `src/` and your domain tests still pass, you're doing Clean Architecture right.