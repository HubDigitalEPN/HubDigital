# Eloquent as Infrastructure (without Leaks)

How to use Eloquent as a data-mapping detail without letting it leak into your domain or use cases.

## Table of Contents

1. [The Two-Model Strategy](#1-the-two-model-strategy)
2. [Mapping Between Domain and Eloquent](#2-mapping-between-domain-and-eloquent)
3. [Handling Relationships Across Aggregates](#3-handling-relationships-across-aggregates)
4. [Dealing with Auto-Timestamps, UUIDs, and Soft Deletes](#4-dealing-with-auto-timestamps-uuids-and-soft-deletes)
5. [Pagination Without Leaking Eloquent](#5-pagination-without-leaking-eloquent)
6. [Optimistic Locking & Concurrency](#6-optimistic-locking--concurrency)
7. [When to Break the Rules](#7-when-to-break-the-rules)

---

## 1. The Two-Model Strategy

In Clean Architecture Laravel, you maintain **two parallel class hierarchies**:

| Domain Model | Eloquent Model |
|--------------|----------------|
| `Src\Admissions\Domain\Entities\Student` | `Src\Admissions\Infrastructure\Persistence\Eloquent\Models\StudentEloquentModel` |
| Pure PHP, no framework | Extends `Illuminate\Database\Eloquent\Model` |
| Has behavior (methods like `admit()`) | Anemic — just fillable, casts, relations |
| Immutable timestamps via `DateTimeImmutable` | Uses Carbon |
| Throws domain exceptions | Uses Laravel validation |

The **repository** translates between them.

### Why not just one?

Using the Eloquent model directly as the domain entity means:

- Your domain depends on `Illuminate\*` forever.
- Unit tests require booting Laravel (slow).
- Swapping the ORM is impossible.
- "Lazy loading" surprises leak persistence into the business logic.
- Eloquent's magic (magic methods, dynamic properties) fights type safety.

The price of duplication is worth it for domain purity.

---

## 2. Mapping Between Domain and Eloquent

### The repository does the work

```php
<?php
namespace Src\Admissions\Infrastructure\Persistence\Eloquent\Repositories;

use Src\Admissions\Domain\Entities\Student;
use Src\Admissions\Domain\Repositories\StudentRepository;
use Src\Admissions\Domain\ValueObjects\{StudentId, Email, CompetencePoints, StudentStatus};
use Src\Admissions\Infrastructure\Persistence\Eloquent\Models\StudentEloquentModel;

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
            $this->toRow($student),
        );
    }

    private function toDomain(StudentEloquentModel $m): Student
    {
        return Student::reconstitute(
            id:           StudentId::fromString($m->id),
            email:        Email::fromString($m->email),
            points:       CompetencePoints::of($m->points),
            status:       StudentStatus::from($m->status),
            registeredAt: $m->registered_at->toDateTimeImmutable(),
        );
    }

    private function toRow(Student $s): array
    {
        return [
            'email'         => (string) $s->email(),
            'points'        => $s->points()->value(),
            'status'        => $s->status()->value,
            'registered_at' => $s->registeredAt()->format('Y-m-d H:i:sP'),
        ];
    }
}
```

### The `reconstitute` pattern

Entities should expose a **private constructor** and two named static factories:

1. **`register()` / `place()` / `create()`** — used when creating a NEW aggregate. Triggers invariants AND records events.
2. **`reconstitute()`** — used ONLY by the repository when loading from storage. No events recorded (they already happened in the past).

```php
final class Student
{
    private function __construct(/* ... */) {}

    public static function register(StudentId $id, Email $email): self
    {
        $s = new self($id, $email, CompetencePoints::zero(), StudentStatus::Pending, new \DateTimeImmutable());
        $s->events[] = new StudentRegistered($id, $email);
        return $s;
    }

    public static function reconstitute(
        StudentId $id,
        Email $email,
        CompetencePoints $points,
        StudentStatus $status,
        \DateTimeImmutable $registeredAt,
    ): self {
        return new self($id, $email, $points, $status, $registeredAt);
    }
}
```

This makes it impossible to accidentally create an unfinished or invalid aggregate.

---

## 3. Handling Relationships Across Aggregates

### Inside one aggregate: embedded

If `OrderItem` is part of the `Order` aggregate, load all items when loading the order:

```php
public function findById(OrderId $id): ?Order
{
    $row = OrderEloquentModel::with('items')->find((string) $id);
    if (!$row) return null;

    $order = Order::reconstitute(
        OrderId::fromString($row->id),
        CustomerId::fromString($row->customer_id),
        OrderStatus::from($row->status),
    );

    foreach ($row->items as $item) {
        $order->restoreItem(
            sku: $item->sku,
            qty: $item->qty,
            unitPrice: Money::of($item->unit_price_cents, $item->currency),
        );
    }

    return $order;
}
```

### Across aggregates: ID only

`Order` stores `CustomerId`, not a `Customer` object. If the use case needs customer data, it explicitly asks the `CustomerRepository`:

```php
final class PlaceOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
        private CustomerRepository $customers,
    ) {}

    public function handle(PlaceOrderInput $in): PlaceOrderOutput
    {
        $customer = $this->customers->findById(CustomerId::fromString($in->customerId))
            ?? throw new CustomerNotFoundException();

        // Do NOT store $customer inside Order. Only $customer->id().
        $order = Order::place(OrderId::generate(), $customer->id());
        /* ... */
    }
}
```

---

## 4. Dealing with Auto-Timestamps, UUIDs, and Soft Deletes

### Timestamps

- Use `DateTimeImmutable` in Domain.
- Let Eloquent's `created_at`/`updated_at` do infrastructure bookkeeping.
- If a timestamp has business meaning (e.g., `published_at`, `submitted_at`), model it as a value object or property on the domain entity and persist it in a dedicated column.

### UUIDs vs auto-increment

Prefer UUIDs for aggregate IDs because:
- The domain can generate them without hitting the DB (via `StudentId::generate()`).
- They don't reveal ordering or volume.
- They keep persistence concerns out of the domain.

```php
<?php
final readonly class StudentId
{
    private function __construct(public string $value) {}

    public static function generate(): self
    {
        return new self(\Illuminate\Support\Str::uuid()->toString());
    }

    public static function fromString(string $id): self
    {
        if (!\Illuminate\Support\Str::isUuid($id)) {
            throw new \InvalidArgumentException("Invalid StudentId: {$id}");
        }
        return new self($id);
    }

    public function __toString(): string { return $this->value; }
}
```

Note: `Str::uuid()` and `Str::isUuid()` are Laravel helpers — this is acceptable because the class lives in Domain **only** if you consider Laravel as "PHP stdlib alternatives" (some teams accept this trade-off). Strict interpretation: move ID generation to a domain port `IdentityGenerator` implemented in infrastructure.

### Soft deletes

Soft deletes are a **persistence detail**. Model explicit state instead:

```php
// ❌ Bad: domain knows about soft deletes
class Student { use SoftDeletes; }

// ✅ Good: domain models lifecycle explicitly
enum StudentStatus: string {
    case Active = 'active';
    case Archived = 'archived';
    case Withdrawn = 'withdrawn';
}

class Student {
    public function archive(): void {
        $this->status = StudentStatus::Archived;
        $this->events[] = new StudentArchived($this->id);
    }
}
```

---

## 5. Pagination Without Leaking Eloquent

Laravel's `LengthAwarePaginator` is an infrastructure type. Don't return it from use cases.

### Pattern: Paginated DTO

```php
<?php
namespace Src\Shared\Application;

/**
 * @template T
 */
final readonly class Paginated
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    public function totalPages(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }
}
```

### Query interface

```php
<?php
namespace Src\Sales\Application\Queries;

use Src\Shared\Application\Paginated;

interface OrderListQuery
{
    /** @return Paginated<OrderListItemDto> */
    public function forCustomer(string $customerId, int $page, int $perPage): Paginated;
}
```

### Infrastructure impl

```php
final class SqlOrderListQuery implements OrderListQuery
{
    public function forCustomer(string $customerId, int $page, int $perPage): Paginated
    {
        $paginator = OrderEloquentModel::query()
            ->where('customer_id', $customerId)
            ->latest()
            ->paginate(perPage: $perPage, page: $page);

        $items = array_map(
            fn($row) => new OrderListItemDto(
                id: $row->id,
                status: $row->status,
                total: $row->total_cents,
            ),
            $paginator->items(),
        );

        return new Paginated(
            items: $items,
            total: $paginator->total(),
            page: $paginator->currentPage(),
            perPage: $paginator->perPage(),
        );
    }
}
```

---

## 6. Optimistic Locking & Concurrency

Aggregates should carry a version number to detect concurrent modifications:

```php
final class Order
{
    public function __construct(
        private readonly OrderId $id,
        /* ... */
        private int $version = 0,
    ) {}

    public function version(): int { return $this->version; }

    // Version bumps happen inside the repository on save.
}
```

In the repository:

```php
public function save(Order $order): void
{
    $affected = OrderEloquentModel::where('id', (string) $order->id())
        ->where('version', $order->version())
        ->update([
            'status'  => $order->status()->value,
            'version' => $order->version() + 1,
            /* ... */
        ]);

    if ($affected === 0) {
        // Either the order doesn't exist yet, or there was a version conflict.
        if (OrderEloquentModel::where('id', (string) $order->id())->exists()) {
            throw new ConcurrencyException($order->id());
        }
        // Insert new
        OrderEloquentModel::create([/* ... */ 'version' => 1]);
    }
}
```

---

## 7. When to Break the Rules

Clean Architecture has a cost. You don't always need to pay it.

### Pragmatic exceptions

- **Throwaway tooling / admin scripts.** Use Eloquent directly.
- **Purely CRUD screens** with no business rules. A resource controller + form request + Eloquent model is fine.
- **Reporting / analytics endpoints.** Skip the write-side aggregate — query directly against the DB.
- **Prototyping / MVPs.** Start with standard Laravel. Refactor to Clean Architecture when real complexity emerges.

### Hybrid approach

It's perfectly legitimate to have **both** styles in the same app:

- `src/Sales/` — full Clean Architecture because it's the core business domain.
- `app/Http/Controllers/Admin/UserController.php` — plain Laravel resource controller because it's CRUD for admins.

The rule: **invest Clean Architecture where complexity and change are expected**. Don't build cathedrals around one-line features.

---

## Summary

- Keep two classes: a pure domain entity and an anemic Eloquent model.
- The repository translates between them via `toDomain()` and `toRow()`.
- Reference other aggregates by ID, not by object.
- Domain uses `DateTimeImmutable`, not Carbon.
- Soft deletes are a persistence detail — model lifecycle states explicitly.
- Return `Paginated<T>` DTOs from queries, not `LengthAwarePaginator`.
- Use optimistic locking (version columns) for aggregate integrity.
- Break the rules pragmatically for CRUD screens and reporting.
