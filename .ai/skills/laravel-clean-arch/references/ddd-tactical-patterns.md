# DDD Tactical Patterns in Laravel

Deep dive into tactical Domain-Driven Design patterns and their implementation within a Laravel Clean Architecture codebase.

## Table of Contents

1. [Entity vs. Value Object](#1-entity-vs-value-object)
2. [Aggregates and Aggregate Roots](#2-aggregates-and-aggregate-roots)
3. [Domain Events](#3-domain-events)
4. [Domain Services](#4-domain-services)
5. [Specifications](#5-specifications)
6. [Factories](#6-factories)
7. [Repositories](#7-repositories)

---

## 1. Entity vs. Value Object

### Decision Matrix

| Question | Yes → Entity | Yes → Value Object |
|----------|--------------|--------------------|
| Does identity matter over time? | ✅ | ❌ |
| Are two instances equal because their attributes are equal? | ❌ | ✅ |
| Does the object mutate? | Often ✅ | ❌ (immutable) |
| Do you need to track history/lifecycle? | ✅ | ❌ |

### Examples

**Entity:** `Student` — two students with same email are still different people.

**Value Object:** `Money`, `Email`, `Address`, `DateRange`, `CompetencePoints`.

### Value Object Template

```php
<?php
declare(strict_types=1);

namespace Src\Shared\Domain\ValueObjects;

final readonly class Money
{
    private function __construct(
        public int $amountInCents,
        public string $currency,
    ) {}

    public static function of(int $cents, string $currency): self
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
        if (!in_array($currency, ['USD', 'EUR', 'NOK'], true)) {
            throw new \InvalidArgumentException("Unsupported currency: {$currency}");
        }
        return new self($cents, $currency);
    }

    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->amountInCents > $other->amountInCents;
    }

    public function equals(self $other): bool
    {
        return $this->amountInCents === $other->amountInCents
            && $this->currency === $other->currency;
    }

    private function ensureSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \DomainException("Currency mismatch: {$this->currency} vs {$other->currency}");
        }
    }
}
```

Key traits: `readonly`, private constructor + named static factory, self-validation, arithmetic returns new instances (immutable).

---

## 2. Aggregates and Aggregate Roots

An **aggregate** is a cluster of entities and value objects treated as a single consistency boundary. The **aggregate root** is the only entry point — external code may only reference the root, never its internals.

### Rules

1. **One repository per aggregate root.** No `OrderItemRepository` — access items via the `Order` root.
2. **Transactional boundaries = aggregate boundaries.** One transaction modifies one aggregate.
3. **Reference other aggregates by ID only.** Never hold a direct reference to another aggregate root.
4. **Invariants are enforced by the root.**

### Example: `Order` as aggregate root

```php
<?php
namespace Src\Sales\Domain\Entities;

use Src\Sales\Domain\ValueObjects\{OrderId, CustomerId, Money, OrderStatus};

final class Order
{
    /** @var list<OrderItem> */
    private array $items = [];

    /** @var list<\Src\Shared\Domain\DomainEvent> */
    private array $events = [];

    public function __construct(
        private readonly OrderId $id,
        private readonly CustomerId $customerId,
        private OrderStatus $status,
    ) {}

    public static function place(OrderId $id, CustomerId $customerId): self
    {
        $order = new self($id, $customerId, OrderStatus::Draft);
        $order->events[] = new Events\OrderPlaced($id, $customerId);
        return $order;
    }

    public function addItem(string $sku, int $qty, Money $unitPrice): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new \DomainException('Cannot modify a non-draft order');
        }
        if ($qty < 1) {
            throw new \InvalidArgumentException('Quantity must be >= 1');
        }
        $this->items[] = new OrderItem($sku, $qty, $unitPrice);
    }

    public function total(): Money
    {
        return array_reduce(
            $this->items,
            fn(Money $carry, OrderItem $i) => $carry->add($i->subtotal()),
            Money::of(0, 'USD'),
        );
    }

    public function submit(): void
    {
        if (count($this->items) === 0) {
            throw new \DomainException('Cannot submit empty order');
        }
        $this->status = OrderStatus::Submitted;
        $this->events[] = new Events\OrderSubmitted($this->id, $this->total());
    }

    public function id(): OrderId { return $this->id; }
    public function customerId(): CustomerId { return $this->customerId; }
    public function status(): OrderStatus { return $this->status; }
    /** @return list<OrderItem> */
    public function items(): array { return $this->items; }

    /** @return list<\Src\Shared\Domain\DomainEvent> */
    public function pullEvents(): array
    {
        $e = $this->events;
        $this->events = [];
        return $e;
    }
}
```

`OrderItem` is an **internal entity** of the `Order` aggregate — it exists only as part of an order, accessible only through the root.

### Why reference other aggregates by ID?

Imagine `Order` holds `Customer $customer` directly. Now:
- Loading an Order always loads a Customer (even if you don't need it).
- Changing a Customer inside an Order blurs transactional boundaries.
- Serialization becomes a nightmare.

Store only `CustomerId`. If you need customer data, ask `CustomerRepository` for it.

---

## 3. Domain Events

Events capture **things that have happened** in the domain.

### Base class

```php
<?php
namespace Src\Shared\Domain;

abstract class DomainEvent
{
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable();
    }

    abstract public function eventName(): string;
}
```

### Concrete event

```php
<?php
namespace Src\Sales\Domain\Events;

use Src\Shared\Domain\DomainEvent;
use Src\Sales\Domain\ValueObjects\{OrderId, Money};

final class OrderSubmitted extends DomainEvent
{
    public function __construct(
        public readonly OrderId $orderId,
        public readonly Money $total,
    ) {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'sales.order.submitted';
    }
}
```

### Recording pattern (do NOT dispatch from inside entities)

❌ **Bad** — entity calls Laravel:
```php
public function submit(): void {
    event(new OrderSubmitted(...));  // Couples domain to Laravel!
}
```

✅ **Good** — entity records, handler dispatches:
```php
// In entity:
$this->events[] = new OrderSubmitted($this->id, $this->total());

// In use case:
$order->submit();
$this->orderRepository->save($order);
foreach ($order->pullEvents() as $event) {
    $this->eventPublisher->publish($event);
}
```

### Bridging to Laravel's event system

```php
<?php
namespace Src\Shared\Infrastructure\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Src\Shared\Domain\DomainEvent;
use Src\Shared\Application\Ports\EventPublisherPort;

final class LaravelEventPublisher implements EventPublisherPort
{
    public function __construct(private readonly Dispatcher $dispatcher) {}

    public function publish(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
```

Now any Laravel listener can react:

```php
Event::listen(OrderSubmitted::class, function (OrderSubmitted $e) {
    SendOrderConfirmationEmail::dispatch($e->orderId);
});
```

---

## 4. Domain Services

Use when an operation doesn't naturally belong to a single entity or value object.

### Rule of thumb

If you find yourself writing `Customer::canPurchase(Product, Money)`, ask: does this logic involve equal rights over multiple aggregates? If yes → it's a **domain service**.

```php
<?php
namespace Src\Sales\Domain\Services;

use Src\Sales\Domain\Entities\{Customer, Order};
use Src\Sales\Domain\ValueObjects\Money;

final class PurchasingPowerService
{
    public function canAfford(Customer $customer, Order $order): bool
    {
        return $customer->creditLimit()->isGreaterThan($order->total());
    }
}
```

A domain service is:
- **Stateless** (or holds only configuration)
- Takes domain objects in, returns domain objects out
- Never talks to the database or Laravel

---

## 5. Specifications

The Specification pattern encapsulates business rules as first-class objects you can combine.

```php
<?php
namespace Src\Shared\Domain;

/**
 * @template T
 */
interface Specification
{
    /** @param T $candidate */
    public function isSatisfiedBy(mixed $candidate): bool;
}
```

### Example

```php
<?php
namespace Src\Sales\Domain\Specifications;

use Src\Sales\Domain\Entities\Customer;
use Src\Shared\Domain\Specification;

/** @implements Specification<Customer> */
final class IsVipCustomer implements Specification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate instanceof Customer
            && $candidate->totalPurchases()->isGreaterThan(Money::of(100_000_00, 'USD'));
    }
}

final class HasGoodCredit implements Specification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate instanceof Customer
            && $candidate->creditScore() >= 700;
    }
}
```

### Composable specs

```php
abstract class CompositeSpec implements Specification
{
    public function and(Specification $other): Specification {
        return new AndSpec($this, $other);
    }
    public function or(Specification $other): Specification {
        return new OrSpec($this, $other);
    }
}
```

Usage:
```php
$canGetDiscount = (new IsVipCustomer())->and(new HasGoodCredit());
if ($canGetDiscount->isSatisfiedBy($customer)) { /* ... */ }
```

---

## 6. Factories

When aggregate creation is complex, extract into a factory (either a static method on the aggregate root or a dedicated factory class).

### Static factory method (preferred for simple cases)

```php
public static function place(OrderId $id, CustomerId $customer): self { /* ... */ }
```

### Dedicated factory class (for complex assembly)

```php
<?php
namespace Src\Sales\Domain\Factories;

final class OrderFactory
{
    public function __construct(
        private readonly CatalogPort $catalog,
        private readonly PricingService $pricing,
    ) {}

    public function createFromCart(CustomerId $customerId, array $cartItems): Order
    {
        $order = Order::place(OrderId::generate(), $customerId);
        foreach ($cartItems as $item) {
            $product = $this->catalog->findBySku($item['sku']);
            $price = $this->pricing->priceFor($product, $customerId);
            $order->addItem($item['sku'], $item['qty'], $price);
        }
        return $order;
    }
}
```

---

## 7. Repositories

### Interface lives in Domain

```php
<?php
namespace Src\Sales\Domain\Repositories;

use Src\Sales\Domain\Entities\Order;
use Src\Sales\Domain\ValueObjects\{OrderId, CustomerId};

interface OrderRepository
{
    public function findById(OrderId $id): ?Order;
    /** @return list<Order> */
    public function findByCustomer(CustomerId $customer): array;
    public function save(Order $order): void;
    public function nextIdentity(): OrderId;
}
```

### Rules

- **One repository per aggregate root.** Not one per table.
- **Repositories return domain objects,** never Eloquent models.
- **Queries that return DTOs for reads should live in a separate `Query` / read model** — don't cram read-specific SQL into a write-focused repository (CQRS-lite).

### Read models (CQRS-lite)

For complex read-heavy screens, skip the aggregate and query optimized views:

```php
<?php
namespace Src\Sales\Application\Queries;

interface OrderListQuery
{
    /** @return list<OrderListItemDto> */
    public function forCustomer(string $customerId, int $page, int $perPage): array;
}

// Infrastructure implementation can use DB::table()->join()-> ... directly
final class SqlOrderListQuery implements OrderListQuery
{
    public function forCustomer(string $customerId, int $page, int $perPage): array
    {
        return DB::table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('customer_id', $customerId)
            ->select(['orders.id', 'orders.status', 'orders.total_cents', 'customers.name'])
            ->paginate($perPage, page: $page)
            ->items();
    }
}
```

This gives you write-side purity (aggregates) AND read-side performance (direct queries), without contaminating either.

---

## Summary

- **Value Objects** are immutable, self-validating, and compared by value.
- **Aggregates** define consistency boundaries; one repository per root.
- **Domain Events** are recorded inside entities, dispatched by handlers — never from the entity itself.
- **Domain Services** hold stateless cross-entity logic.
- **Specifications** capture composable business rules.
- **Factories** encapsulate complex aggregate creation.
- **Repositories** are one-per-aggregate-root and return domain objects; use separate Queries for read models.
