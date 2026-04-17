# Testing Recipes for Laravel Clean Architecture

Concrete templates and patterns for testing each layer of a Clean Architecture Laravel application. Uses **Pest** (primary) with equivalent PHPUnit notes.

## Table of Contents

1. [Testing Pyramid for CA + Laravel](#1-testing-pyramid-for-ca--laravel)
2. [Unit Tests — Domain](#2-unit-tests--domain)
3. [Unit Tests — Application (Use Cases)](#3-unit-tests--application-use-cases)
4. [Integration Tests — Infrastructure](#4-integration-tests--infrastructure)
5. [Feature Tests — HTTP](#5-feature-tests--http)
6. [Test Doubles Library](#6-test-doubles-library)
7. [Mutation Testing](#7-mutation-testing)
8. [Contract Tests for Ports](#8-contract-tests-for-ports)

---

## 1. Testing Pyramid for CA + Laravel

```
              /\
             /  \      Feature (few, slow, expensive)
            /----\     - Full HTTP stack, real DB
           /      \    - "Happy path" + critical errors only
          /--------\
         /          \  Integration (medium count)
        /------------\ - Repositories, gateways, adapters
       /              \- Uses real DB (SQLite in-memory OK)
      /----------------\
     /                  \  Unit — Application (many)
    /--------------------\ - Handlers with fake ports/repos
   /                      \- Fast, no Laravel boot
  /------------------------\
 /                          \  Unit — Domain (lots)
/____________________________\ - Entities, VOs, domain services
                                - Pure PHP, milliseconds
```

### Directory layout

```
tests/
├── Unit/
│   ├── Admissions/
│   │   ├── Domain/
│   │   │   ├── StudentTest.php
│   │   │   ├── CompetencePointsTest.php
│   │   │   └── AdmissionEvaluatorTest.php
│   │   └── Application/
│   │       └── EvaluateAdmissionHandlerTest.php
│   └── Support/
│       └── Doubles/
│           ├── InMemoryStudentRepository.php
│           └── FakeEventPublisher.php
├── Integration/
│   └── Admissions/
│       └── Infrastructure/
│           ├── EloquentStudentRepositoryTest.php
│           └── HttpGradesGatewayTest.php
├── Feature/
│   └── Admissions/
│       └── EvaluateAdmissionEndpointTest.php
├── Pest.php
└── TestCase.php
```

---

## 2. Unit Tests — Domain

Fastest, most numerous. **No `Tests\TestCase`** — extend nothing, boot nothing.

### Pest configuration

```php
<?php
// tests/Pest.php
uses()->in('Feature', 'Integration');  // Laravel boot only for these
// Unit tests use no uses() → plain PHP
```

### Value Object test

```php
<?php
// tests/Unit/Admissions/Domain/CompetencePointsTest.php
declare(strict_types=1);

use Src\Admissions\Domain\ValueObjects\CompetencePoints;

it('creates points from a non-negative integer', function () {
    $p = CompetencePoints::of(85);
    expect($p->value())->toBe(85);
});

it('rejects negative values', function () {
    CompetencePoints::of(-1);
})->throws(\InvalidArgumentException::class);

it('adds two points values', function () {
    $a = CompetencePoints::of(40);
    $b = CompetencePoints::of(25);
    expect($a->add($b)->value())->toBe(65);
});

it('is value-equal when amounts match', function () {
    expect(CompetencePoints::of(10)->equals(CompetencePoints::of(10)))->toBeTrue();
    expect(CompetencePoints::of(10)->equals(CompetencePoints::of(11)))->toBeFalse();
});

it('is immutable — add returns new instance', function () {
    $original = CompetencePoints::of(10);
    $result = $original->add(CompetencePoints::of(5));
    expect($original->value())->toBe(10);  // unchanged
    expect($result->value())->toBe(15);
});
```

### Entity test

```php
<?php
// tests/Unit/Admissions/Domain/StudentTest.php
declare(strict_types=1);

use Src\Admissions\Domain\Entities\Student;
use Src\Admissions\Domain\ValueObjects\{StudentId, Email, CompetencePoints, StudentStatus};
use Src\Admissions\Domain\Events\{StudentRegistered, StudentAdmitted};
use Src\Admissions\Domain\Exceptions\InvalidStudentStateException;

function makeStudent(int $points = 0): Student
{
    $s = Student::register(
        StudentId::fromString('11111111-1111-1111-1111-111111111111'),
        Email::fromString('test@example.com'),
    );
    if ($points > 0) {
        $s->pullEvents();  // discard registration event for cleaner assertions
        $s->addPoints(CompetencePoints::of($points));
    }
    return $s;
}

it('records a StudentRegistered event on registration', function () {
    $s = Student::register(StudentId::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'), Email::fromString('a@b.c'));
    $events = $s->pullEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(StudentRegistered::class);
});

it('admits a student with sufficient points', function () {
    $s = makeStudent(points: 85);
    $s->admit();

    expect($s->status())->toBe(StudentStatus::Admitted)
        ->and($s->pullEvents())->toHaveCount(1)
        ->and($s->pullEvents()[0] ?? null)->toBeNull();  // events consumed
});

it('rejects admission when points insufficient', function () {
    $s = makeStudent(points: 10);
    $s->admit();
})->throws(InvalidStudentStateException::class);

it('cannot add points to a rejected student', function () {
    $s = makeStudent(points: 10);
    // simulate rejection (via repo reconstitution or domain method)
    $s = Student::reconstitute(
        StudentId::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'),
        Email::fromString('r@x.y'),
        CompetencePoints::of(0),
        StudentStatus::Rejected,
        new DateTimeImmutable(),
    );
    $s->addPoints(CompetencePoints::of(5));
})->throws(InvalidStudentStateException::class);
```

### Domain Service test

```php
<?php
use Src\Admissions\Domain\Services\AdmissionEvaluator;
use Src\Admissions\Domain\ValueObjects\Quota;

it('can admit when quota available and points meet minimum', function () {
    $evaluator = new AdmissionEvaluator();
    $student = makeStudent(points: 90);
    $quota = Quota::of(remaining: 5);

    expect($evaluator->canAdmit($student, $quota))->toBeTrue();
});

it('cannot admit when quota is exhausted', function () {
    $evaluator = new AdmissionEvaluator();
    $student = makeStudent(points: 90);
    $quota = Quota::of(remaining: 0);

    expect($evaluator->canAdmit($student, $quota))->toBeFalse();
});
```

---

## 3. Unit Tests — Application (Use Cases)

Also runs without Laravel. Use in-memory test doubles for ports.

```php
<?php
// tests/Unit/Admissions/Application/EvaluateAdmissionHandlerTest.php
declare(strict_types=1);

use Src\Admissions\Application\UseCases\EvaluateAdmission\{
    EvaluateAdmissionHandler, EvaluateAdmissionInput
};
use Src\Admissions\Domain\Entities\Student;
use Src\Admissions\Domain\Services\AdmissionEvaluator;
use Src\Admissions\Domain\ValueObjects\{StudentId, Email, CompetencePoints, Quota};
use Tests\Unit\Support\Doubles\{
    InMemoryStudentRepository, FakeEventPublisher, FakeQuotaProvider
};

beforeEach(function () {
    $this->repo = new InMemoryStudentRepository();
    $this->events = new FakeEventPublisher();
    $this->quotas = new FakeQuotaProvider(['CS101' => Quota::of(5)]);
    $this->handler = new EvaluateAdmissionHandler(
        $this->repo,
        new AdmissionEvaluator(),
        $this->quotas,
        $this->events,
    );
});

it('admits a qualified student and publishes event', function () {
    $student = Student::register(
        StudentId::fromString('11111111-1111-1111-1111-111111111111'),
        Email::fromString('a@b.c'),
    );
    $student->addPoints(CompetencePoints::of(90));
    $this->repo->save($student);
    $student->pullEvents();  // clear registration events

    $output = $this->handler->handle(new EvaluateAdmissionInput(
        studentId: (string) $student->id(),
        programCode: 'CS101',
    ));

    expect($output->admitted)->toBeTrue()
        ->and($output->status)->toBe('admitted')
        ->and($this->events->published)->toHaveCount(1);
});

it('throws when student not found', function () {
    $this->handler->handle(new EvaluateAdmissionInput(
        studentId: '00000000-0000-0000-0000-000000000000',
        programCode: 'CS101',
    ));
})->throws(\Src\Admissions\Domain\Exceptions\StudentNotFoundException::class);

it('does not admit when quota is exhausted', function () {
    $this->quotas->set('CS101', Quota::of(0));
    // ... setup student with 90 points
    // ... call handler
    // expect output->admitted to be false and no events published
});
```

---

## 4. Integration Tests — Infrastructure

These boot Laravel and hit a real database.

### Pest + Laravel base test case

```php
<?php
// tests/TestCase.php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\CreatesApplication;
}
```

### Repository integration test

```php
<?php
// tests/Integration/Admissions/Infrastructure/EloquentStudentRepositoryTest.php
declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Admissions\Domain\Entities\Student;
use Src\Admissions\Domain\ValueObjects\{StudentId, Email, CompetencePoints};
use Src\Admissions\Infrastructure\Persistence\Eloquent\Repositories\EloquentStudentRepository;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repo = app(EloquentStudentRepository::class);
});

it('saves and reconstitutes a Student aggregate', function () {
    $student = Student::register(
        $this->repo->nextIdentity(),
        Email::fromString('integration@test.dev'),
    );
    $student->addPoints(CompetencePoints::of(75));

    $this->repo->save($student);
    $loaded = $this->repo->findById($student->id());

    expect($loaded)->not->toBeNull()
        ->and($loaded->email()->value)->toBe('integration@test.dev')
        ->and($loaded->points()->value())->toBe(75);
});

it('returns null for unknown IDs', function () {
    $result = $this->repo->findById(StudentId::fromString('00000000-0000-0000-0000-000000000000'));
    expect($result)->toBeNull();
});

it('updates an existing Student without duplicating', function () {
    $student = Student::register($this->repo->nextIdentity(), Email::fromString('x@y.z'));
    $this->repo->save($student);

    $student->addPoints(CompetencePoints::of(50));
    $this->repo->save($student);

    $loaded = $this->repo->findById($student->id());
    expect($loaded->points()->value())->toBe(50);

    // Should only have 1 row in the DB
    expect(\DB::table('students')->where('id', (string) $student->id())->count())->toBe(1);
});
```

### HTTP Gateway integration test

```php
<?php
use Illuminate\Support\Facades\Http;
use Src\Admissions\Infrastructure\Gateways\HttpGradesGateway;

it('fetches grades from the external API', function () {
    Http::fake([
        'grades.example.com/students/*/grades' => Http::response([
            'data' => [['subject' => 'math', 'score' => 90]],
        ], 200),
    ]);

    $gateway = new HttpGradesGateway('https://grades.example.com');
    $grades = $gateway->fetchGradesFor('stu-1');

    expect($grades)->toHaveCount(1)
        ->and($grades[0]['score'])->toBe(90);
});

it('retries on transient failures and eventually succeeds', function () {
    Http::fake([
        'grades.example.com/*' => Http::sequence()
            ->pushStatus(500)
            ->pushStatus(500)
            ->push(['data' => []], 200),
    ]);

    $gateway = new HttpGradesGateway('https://grades.example.com');
    $result = $gateway->fetchGradesFor('stu-1');

    expect($result)->toBe([]);
});
```

---

## 5. Feature Tests — HTTP

End-to-end through the HTTP stack. Keep these **few**; they're slow.

```php
<?php
// tests/Feature/Admissions/EvaluateAdmissionEndpointTest.php
declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('evaluates admission via API and returns 200', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['admissions.evaluate']);

    // Seed a student through the repository (NOT Eloquent directly)
    $repo = app(\Src\Admissions\Domain\Repositories\StudentRepository::class);
    $student = \Src\Admissions\Domain\Entities\Student::register(
        $repo->nextIdentity(),
        \Src\Admissions\Domain\ValueObjects\Email::fromString('e2e@test.dev'),
    );
    $student->addPoints(\Src\Admissions\Domain\ValueObjects\CompetencePoints::of(95));
    $repo->save($student);

    $response = postJson('/api/admissions/evaluate', [
        'student_id' => (string) $student->id(),
        'program_code' => 'CS101',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.admitted', true)
        ->assertJsonPath('data.status', 'admitted');
});

it('returns 422 when business rules fail', function () {
    // ... authenticate, seed student with 0 points, call endpoint
    // expect 422 with error.code = 'invariant_violation'
});

it('returns 404 when student does not exist', function () {
    Sanctum::actingAs(User::factory()->create(), ['admissions.evaluate']);

    postJson('/api/admissions/evaluate', [
        'student_id' => '00000000-0000-0000-0000-000000000000',
        'program_code' => 'CS101',
    ])->assertStatus(404);
});

it('requires authentication', function () {
    postJson('/api/admissions/evaluate', [
        'student_id' => 'doesnt-matter',
        'program_code' => 'CS101',
    ])->assertStatus(401);
});
```

---

## 6. Test Doubles Library

Keep reusable in-memory implementations in `tests/Unit/Support/Doubles/`.

### In-memory repository

```php
<?php
// tests/Unit/Support/Doubles/InMemoryStudentRepository.php
namespace Tests\Unit\Support\Doubles;

use Src\Admissions\Domain\Entities\Student;
use Src\Admissions\Domain\Repositories\StudentRepository;
use Src\Admissions\Domain\ValueObjects\StudentId;

final class InMemoryStudentRepository implements StudentRepository
{
    /** @var array<string, Student> */
    public array $data = [];

    public function findById(StudentId $id): ?Student
    {
        return $this->data[(string) $id] ?? null;
    }

    public function save(Student $student): void
    {
        $this->data[(string) $student->id()] = $student;
    }

    public function nextIdentity(): StudentId
    {
        return StudentId::fromString(
            sprintf('%08x-%04x-%04x-%04x-%012x',
                random_int(0, 0xffffffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffffffffffff),
            ),
        );
    }
}
```

### Fake event publisher

```php
<?php
namespace Tests\Unit\Support\Doubles;

use Src\Shared\Application\Ports\EventPublisherPort;
use Src\Shared\Domain\DomainEvent;

final class FakeEventPublisher implements EventPublisherPort
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(DomainEvent $event): void
    {
        $this->published[] = $event;
    }

    public function assertPublished(string $eventClass): bool
    {
        foreach ($this->published as $e) {
            if ($e instanceof $eventClass) return true;
        }
        return false;
    }
}
```

### Principles

- **Prefer fakes over mocks.** A fake is a working in-memory implementation; a mock has brittle expectations. Fakes let your tests read like documentation.
- **One fake per port**, stored in `Support/Doubles/`, shared by all tests.
- Fakes are part of the test suite — they have real logic and should be simple enough not to need tests themselves.

---

## 7. Mutation Testing

Use [Infection](https://infection.github.io/) to verify your tests actually catch bugs:

```bash
composer require --dev infection/infection
./vendor/bin/infection --threads=8 --min-msi=85
```

Mutation testing mutates your code (e.g., `>` → `>=`) and checks if any test fails. An MSI (Mutation Score Indicator) above 85% on the **domain layer** is a strong signal of good test quality. Don't aim for 100% on infrastructure — it's lower-leverage.

---

## 8. Contract Tests for Ports

When you have multiple implementations of a port (e.g., `EloquentStudentRepository` and `InMemoryStudentRepository`), write **contract tests** once and run them against every implementation:

```php
<?php
// tests/Contract/StudentRepositoryContract.php
abstract class StudentRepositoryContract extends \PHPUnit\Framework\TestCase
{
    abstract protected function createRepository(): StudentRepository;

    public function test_save_then_find_returns_equal_aggregate(): void
    {
        $repo = $this->createRepository();
        $student = Student::register($repo->nextIdentity(), Email::fromString('x@y.z'));
        $student->addPoints(CompetencePoints::of(42));

        $repo->save($student);
        $loaded = $repo->findById($student->id());

        $this->assertNotNull($loaded);
        $this->assertTrue($loaded->email()->equals($student->email()));
        $this->assertSame(42, $loaded->points()->value());
    }

    // ... more contract tests
}

// Concrete implementations:
final class InMemoryStudentRepositoryTest extends StudentRepositoryContract {
    protected function createRepository(): StudentRepository {
        return new InMemoryStudentRepository();
    }
}

final class EloquentStudentRepositoryContractTest extends StudentRepositoryContract {
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    protected function createRepository(): StudentRepository {
        return app(EloquentStudentRepository::class);
    }
}
```

This guarantees your in-memory fake actually obeys the same contract as production — no drift.

---

## Summary

- **Domain tests**: pure PHP, milliseconds, run constantly.
- **Application tests**: handlers + in-memory fakes, fast, no Laravel boot.
- **Integration tests**: real DB, real HTTP, cover infrastructure adapters.
- **Feature tests**: few, targeted, cover critical HTTP journeys only.
- **Keep reusable fakes** in `tests/Unit/Support/Doubles/`.
- **Prefer fakes over mocks** — readable, maintainable, reflect real behavior.
- **Mutation testing** (Infection) validates test quality on the domain.
- **Contract tests** ensure multiple implementations of a port stay in sync.
