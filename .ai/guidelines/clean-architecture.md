# clean-architecture.md
# Clean Architecture Conventions — Hub Digital

> **Purpose of this file:** non-negotiable rules that Claude Code must follow
> at ALL times when creating or modifying Hub Digital code. This is not a tutorial.
> For full examples, templates, and explanations, activate the
> `laravel-clean-architecture` skill and its references in `references/`.

---

## 1. The three modules and their bounded contexts

Hub Digital uses **nwidart/laravel-modules**. Each module is an independent bounded context
with its own internal layers.

| Module (directory) | Bounded Context | Root namespace |
|---------------------|-----------------|----------------|
| `Modules/LoanReceptionManagement/` | Specimen loan and reception management | `Modules\LoanReceptionManagement` |
| `Modules/InventoryCollectionManagement/` | Collection inventory, locations, and traceability | `Modules\InventoryCollectionManagement` |
| `Modules/PublicCatalog/` | Public catalog with AI chatbot | `Modules\PublicCatalog` |

**Never:**
- ❌ A module accesses another module's `Domain/` directly.
- ❌ Business code is created outside `Modules/` (except `Shared/`).
- ❌ A fourth module is added without first defining its bounded context.

---

## 2. Internal structure of each module

Each module replicates this structure. Claude must not invent new folders
outside this schema without explicit team approval.

```
Modules/<Module>/
├── src/
│   ├── Domain/
│   │   ├── Entities/
│   │   ├── ValueObjects/
│   │   ├── Services/          # Domain Services (stateless, no framework)
│   │   ├── Events/            # Domain Events
│   │   ├── Repositories/      # Interfaces ONLY — never implementations
│   │   └── Exceptions/
│   │
│   ├── Application/
│   │   ├── UseCases/
│   │   │   └── <UseCaseName>/
│   │   │       ├── <UseCaseName>Handler.php
│   │   │       ├── <UseCaseName>Input.php
│   │   │       └── <UseCaseName>Output.php
│   │   └── Ports/             # Interfaces for external services
│   │
│   └── Infrastructure/
│       ├── Persistence/
│       │   └── Eloquent/
│       │       ├── Models/    # Eloquent models — ONLY here
│       │       └── Repositories/
│       ├── Gateways/
│       └── Notifications/
│
├── Http/
│   ├── Controllers/           # Livewire components and API controllers
│   ├── Requests/              # Form Requests → produce Input DTOs
│   └── Resources/             # Use Case Output → HTTP response
│
├── Providers/
│   └── <Module>ServiceProvider.php   # Registers port → adapter bindings
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Behat/
│
└── composer.json
```

---

## 3. Layer rules — what each class can and cannot import

### Domain — innermost layer, no framework

| ✅ Allowed | ❌ Forbidden |
|-------------|-------------|
| Pure PHP stdlib | Any `Illuminate\*` |
| `DateTimeImmutable` | `Carbon` or `CarbonImmutable` |
| Other classes within the same `Domain/` | Eloquent models |
| Classes from `Shared/Domain/` | Laravel Facades |
| | Classes from `Application/` or `Infrastructure/` |

**The test:** if Laravel is removed from the project, `Domain/` classes must
still compile without error.

### Application — pure orchestration

| ✅ Allowed | ❌ Forbidden |
|-------------|-------------|
| Classes from `Domain/` | Eloquent models directly |
| Interfaces from `Ports/` | Facades (`DB::`, `Mail::`, `Cache::`) |
| Own Input/Output DTOs | Classes from `Infrastructure/` |
| Classes from `Shared/Application/` | Business logic (that belongs in Domain) |

Handlers have no `use Illuminate\*` except in well-justified cases
(e.g. `DB::transaction()` as a transaction adapter — prefer a Port).

### Infrastructure — adapters to the outside world

| ✅ Allowed | ❌ Forbidden |
|-------------|-------------|
| Everything from `Domain/` and `Application/` | Business logic |
| `Illuminate\*`, Eloquent, Carbon | Returning Eloquent models upward |
| Classes from `Shared/Infrastructure/` | Accessing `Domain/` of another module |

### Presentation (Http/, Console/) — delivery, not logic

| ✅ Allowed | ❌ Forbidden |
|-------------|-------------|
| Call a Handler with its Input DTO | Business rules in the controller |
| Form Request → `toInput()` | `DB::` or Eloquent in controllers |
| Resource/Livewire → Output DTO | Returning Eloquent models as responses |
| | Calling more than one Handler per action |

---

## 4. Naming rules

### Use Cases

Each Use Case lives in its own folder with three files:

```
UseCases/LoanRequestSubmission/
├── LoanRequestSubmissionHandler.php   # orchestrates
├── LoanRequestSubmissionInput.php     # readonly, input data
└── LoanRequestSubmissionOutput.php    # readonly, output data
```

- The Use Case name uses a **domain verb + noun** in English:
  `LoanRequestSubmission`, `LoanRequestResolution`, `BoxLocationRegistration`.
- `Handler` is always the suffix of the orchestrating class. Not `Service`, not `Action`,
  not `Manager`.

### Repositories

- Interface in `Domain/Repositories/`: `LoanRequestRepository` (no `I` prefix).
- Implementation in `Infrastructure/Persistence/Eloquent/Repositories/`:
  `EloquentLoanRequestRepository`.
- **One interface per aggregate root.** Not `LoanRequestItemRepository`.

### Eloquent Models

- Name: `<Entity>EloquentModel` — never the same as the domain entity.
- Example: entity `LoanRequest` → model `LoanRequestEloquentModel`.
- Live **only** in `Infrastructure/Persistence/Eloquent/Models/`.

### Value Objects

- `final readonly` class, private constructor, named static factory.
- Descriptive name of the concept, not the type: `SpecimenCode`, `OriginInstitution`,
  not `StringWrapper` or `SpecimenString`.

### Ports

- `Port` suffix for external service interfaces: `NotificationPort`,
  `FileStoragePort`.
- No `I` prefix. No `Interface` suffix.

---

## 5. Domain events — mandatory pattern

Events are **registered** inside the entity. The Handler **dispatches** them
after saving. Never the other way around.

```
Entity::method()           → $this->events[] = new DomainEvent(...)
Handler::handle()          → $repo->save($entity)
                           → foreach ($entity->pullEvents() as $e) { $publisher->publish($e); }
```

**Never** call `event()`, `Event::dispatch()`, or any Facade inside
a domain entity or Value Object.

---

## 6. ServiceProvider per module

Each module has exactly **one** ServiceProvider that registers all its
port → adapter bindings.

```
Modules/<Module>/Providers/<Module>ServiceProvider.php
```

- Registered in `bootstrap/providers.php` (Laravel 13).
- Every new `Ports/` or `Repositories/` interface must have its binding here
  before the Handler can be resolved from the container.
- Do not use `app()->bind()` anywhere else in the module.

---

## 7. Livewire and Flux UI — Presentation layer

Livewire components are **Presentation**, not Application.

- A Livewire component injects the Handler in its constructor or method.
- Business logic lives in the Handler, not in the component.
- The component produces an Input DTO and consumes an Output DTO.
- Components live in `Http/Controllers/` or in `resources/views/livewire/`
  of the module, following the active nwidart convention in the project.

```php
// ✅ Correct
final class SubmitLoanRequestForm extends Component
{
    public function submit(LoanRequestSubmissionHandler $handler): void
    {
        $output = $handler->handle(new LoanRequestSubmissionInput(...));
        // update component state with $output
    }
}

// ❌ Incorrect — business logic in Livewire
final class SubmitLoanRequestForm extends Component
{
    public function submit(): void
    {
        if (LoanRequest::where('researcher_id', ...)->count() >= 3) { ... }
        LoanRequest::create([...]);
    }
}
```

---

## 8. PublicCatalog — additional rules for the AI SDK

The `PublicCatalog` module is the only one that uses the Laravel AI SDK for the chatbot.

- The AI SDK integration lives **only** in `Infrastructure/` of that module.
- The chatbot is exposed as a Livewire component that consumes the stream via
  server-sent events.
- The AI SDK has no presence in `LoanReceptionManagement` or in
  `InventoryCollectionManagement`.
- If the chatbot needs to query collection data, it does so through
  a Port defined in `PublicCatalog/Application/Ports/`, implemented by
  an adapter that calls the `InventoryCollectionManagement` Repository — never
  accessing its `Domain/` directly.

---

## 9. Checklist before marking a class as ready

- [ ] Classes in `Domain/` have no `use Illuminate\*`
- [ ] Classes in `Domain/` do not use `Carbon` — only `DateTimeImmutable`
- [ ] Eloquent Models live in `Infrastructure/Persistence/Eloquent/Models/`
- [ ] The Repository interface is in `Domain/Repositories/`, the implementation in `Infrastructure/`
- [ ] The Handler returns an Output DTO, not an Eloquent Model or domain entity
- [ ] The new Port has its binding in the module's ServiceProvider
- [ ] Domain events are registered in the entity and dispatched in the Handler
- [ ] The controller or Livewire component has ≤ 10 lines per action
- [ ] No module imports classes from another module's `Domain/`
- [ ] The Use Case has all three files: Handler, Input, Output
